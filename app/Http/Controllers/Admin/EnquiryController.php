<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\EnquiryReplied;
use App\Models\Enquiry;
use App\Models\User;
use App\Services\EnquiryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EnquiryController extends Controller implements HasMiddleware
{
    public function __construct(protected EnquiryService $enquiries) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:enquiries.view', only: ['index', 'show']),
            new Middleware('permission:enquiries.create', only: ['create', 'store']),
            new Middleware('permission:enquiries.edit', only: ['reply', 'assign', 'close', 'reopen', 'updateNote']),
            new Middleware('permission:enquiries.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $enquiries = Enquiry::query()
            ->with(['product:id,name,slug', 'assignee:id,name'])
            ->withCount('replies')
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $like = '%'.$request->string('q').'%';
                $query->where(fn (Builder $q) => $q
                    ->where('reference', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('subject', 'like', $like)
                    ->orWhere('message', 'like', $like));
            })
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->input('status')))
            ->when($request->filled('type'), fn (Builder $q) => $q->where('type', $request->input('type')))
            ->when($request->filled('assigned_to'), fn (Builder $q) => $q->where('assigned_to', $request->input('assigned_to')))
            ->when($request->input('flag') === 'unanswered', fn (Builder $q) => $q->unanswered())
            ->when($request->input('flag') === 'mine', fn (Builder $q) => $q->where('assigned_to', auth('admin')->id())->open())
            // Spam is hidden unless asked for, or the inbox becomes useless.
            ->when($request->input('status') !== 'spam', fn (Builder $q) => $q->where('status', '!=', 'spam'))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.enquiries.index', [
            'enquiries' => $enquiries,
            'statuses' => Enquiry::STATUSES,
            'types' => Enquiry::TYPES,
            'staff' => $this->staff(),
            'summary' => [
                'unanswered' => Enquiry::unanswered()->count(),
                'open' => Enquiry::open()->count(),
                'mine' => Enquiry::where('assigned_to', auth('admin')->id())->open()->count(),
            ],
        ]);
    }

    public function show(Enquiry $enquiry): View
    {
        $enquiry->load([
            'product:id,name,slug,sku,price',
            'customer:id,name,email',
            'assignee:id,name',
            'attachments',
            'replies.user:id,name',
            'quotations',
        ]);

        return view('admin.enquiries.show', [
            'enquiry' => $enquiry,
            'statuses' => Enquiry::STATUSES,
            'staff' => $this->staff(),
            'channels' => \App\Models\EnquiryReply::CHANNELS,
        ]);
    }

    /**
     * Staff logging an enquiry that arrived by phone or at the counter.
     */
    public function create(): View
    {
        return view('admin.enquiries.create', [
            'types' => Enquiry::TYPES,
            'sources' => Enquiry::SOURCES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Enquiry::TYPES))],
            'source' => ['required', Rule::in(array_keys(Enquiry::SOURCES))],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{8,20}$/'],
            'city' => ['nullable', 'string', 'max:120'],
            'pincode' => ['nullable', 'regex:/^[1-9][0-9]{5}$/'],
            'subject' => ['nullable', 'string', 'max:180'],
            'message' => ['required', 'string', 'max:5000'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'required_by' => ['nullable', 'date'],
        ]);

        $enquiry = $this->enquiries->create($data + [
            'assigned_to' => auth('admin')->id(),
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.enquiries.show', $enquiry)
            ->with('success', 'Enquiry '.$enquiry->reference.' logged.');
    }

    public function reply(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'channel' => ['required', Rule::in(array_keys(\App\Models\EnquiryReply::CHANNELS))],
        ], [
            'body.required' => 'Write something before saving it to the thread.',
        ]);

        $emailed = false;
        $warning = '';

        // Only an emailed reply actually leaves the building; a phone note
        // records something that already happened.
        if ($data['channel'] === 'email') {
            try {
                Mail::to($enquiry->email)->send(new EnquiryReplied($enquiry, $data['body']));
                $emailed = true;
            } catch (\Throwable $e) {
                Log::error('Enquiry reply email failed', [
                    'enquiry' => $enquiry->reference,
                    'error' => $e->getMessage(),
                ]);

                $warning = ' The email could not be sent — check the mail settings. The reply is saved.';
            }
        }

        $this->enquiries->reply(
            $enquiry,
            $data['body'],
            $data['channel'],
            auth('admin')->user(),
            $emailed,
        );

        $message = match ($data['channel']) {
            'email' => $emailed ? 'Reply emailed to the customer.' : 'Reply saved.',
            'note' => 'Note added. The customer cannot see this.',
            default => 'Added to the thread.',
        };

        return back()->with($warning ? 'error' : 'success', $message.$warning);
    }

    public function assign(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $data = $request->validate([
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);

        $to = isset($data['assigned_to']) ? User::find($data['assigned_to']) : null;

        $this->enquiries->assign($enquiry, $to);

        return back()->with('success', $to
            ? 'Assigned to '.$to->name.'.'
            : 'Assignment cleared.');
    }

    public function close(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['closed', 'spam'])],
            'close_reason' => ['nullable', 'string', 'max:300'],
        ]);

        $this->enquiries->close($enquiry, $data['status'], $data['close_reason'] ?? null);

        return back()->with('success', $data['status'] === 'spam'
            ? 'Marked as spam and hidden from the inbox.'
            : 'Enquiry closed.');
    }

    public function reopen(Enquiry $enquiry): RedirectResponse
    {
        $this->enquiries->reopen($enquiry);

        return back()->with('success', 'Enquiry reopened.');
    }

    public function updateNote(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $enquiry->update(['admin_note' => $data['admin_note'] ?? null]);

        return back()->with('success', 'Note saved.');
    }

    public function destroy(Enquiry $enquiry): RedirectResponse
    {
        if ($enquiry->quotations()->exists()) {
            return back()->with('error',
                'This enquiry has quotations against it, so it cannot be deleted. Close it instead.');
        }

        $reference = $enquiry->reference;
        $enquiry->delete();

        return redirect()->route('admin.enquiries.index')
            ->with('success', 'Enquiry '.$reference.' deleted.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function staff()
    {
        return User::where('is_active', true)
            ->whereHas('roles', fn ($r) => $r->whereIn('name', ['Admin', 'Manager', 'Sales']))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
