<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Services\NewsletterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The mailing list, from the shop's side.
 *
 * Deliberately a list and an export, not a sending tool: FurniCraft is not a
 * mail platform, and the export is what a real one takes.
 */
class NewsletterController extends Controller implements HasMiddleware
{
    public function __construct(protected NewsletterService $newsletter) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:newsletter.view', only: ['index', 'export']),
            new Middleware('permission:newsletter.edit', only: ['unsubscribe']),
        ];
    }

    public function index(Request $request): View
    {
        $subscribers = $this->filtered($request)
            ->paginate(30)
            ->withQueryString();

        return view('admin.newsletter.index', [
            'subscribers' => $subscribers,
            'statuses' => NewsletterSubscriber::STATUSES,
            'summary' => $this->newsletter->counts(),
        ]);
    }

    /**
     * Only people who may actually be sent to are exported by default: an
     * export that quietly includes unsubscribes is how a shop emails someone
     * who asked it not to.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $request->filled('status') || $request->filled('q')
            ? $this->filtered($request)
            : NewsletterSubscriber::mailable()->latest('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['email', 'name', 'status', 'source', 'subscribed_on', 'unsubscribe_url']);

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $subscriber) {
                    fputcsv($out, [
                        $subscriber->email,
                        $subscriber->name,
                        $subscriber->status,
                        $subscriber->source,
                        $subscriber->confirmed_at?->format('Y-m-d') ?? $subscriber->created_at->format('Y-m-d'),
                        // Carried into whatever sends the mail, so every
                        // message can offer one-click unsubscribe.
                        route('newsletter.unsubscribe', $subscriber->unsubscribe_token),
                    ]);
                }
            });

            fclose($out);
        }, 'furnicraft-newsletter-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Taking somebody off on their behalf — they phoned and asked.
     */
    public function unsubscribe(Request $request, NewsletterSubscriber $subscriber): RedirectResponse
    {
        $this->newsletter->unsubscribe($subscriber, $request->input('reason', 'Removed by staff'));

        return back()->with('success', $subscriber->email.' has been taken off the list.');
    }

    /**
     * @return Builder<NewsletterSubscriber>
     */
    protected function filtered(Request $request): Builder
    {
        return NewsletterSubscriber::query()
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $like = '%'.$request->string('q').'%';
                $query->where(fn (Builder $q) => $q
                    ->where('email', 'like', $like)->orWhere('name', 'like', $like));
            })
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->input('status')))
            ->latest('id');
    }
}
