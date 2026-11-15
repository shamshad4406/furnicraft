<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:users.view', only: ['index']),
            new Middleware('permission:users.create', only: ['create', 'store']),
            new Middleware('permission:users.edit', only: ['edit', 'update', 'toggle']),
            new Middleware('permission:users.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $users = User::query()
            ->with('roles')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term);
                });
            })
            ->when($request->filled('role'), fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $request->input('role'))))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->input('status') === 'active'))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $roles = Role::query()->where('guard_name', 'admin')->orderBy('name')->pluck('name');

        return view('admin.users.index', compact('users', 'roles'));
    }

    public function create(): View
    {
        return view('admin.users.form', [
            'user' => new User(['is_active' => true]),
            'roles' => $this->roles(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = new User();
        $user->fill($data);
        $user->is_active = $request->boolean('is_active');
        $user->password = $data['password'];
        $user->email_verified_at = now();

        if ($request->hasFile('avatar')) {
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        $user->save();
        $user->syncRoles([$data['role']]);

        return redirect()->route('admin.users.index')
            ->with('success', $user->name.' has been added as '.$data['role'].'.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', [
            'user' => $user,
            'roles' => $this->roles(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $this->guardLastAdmin($user, $request->boolean('is_active'), $data['role']);

        $user->fill($data);
        $user->is_active = $request->boolean('is_active');

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        $user->save();
        $user->syncRoles([$data['role']]);

        return redirect()->route('admin.users.index')
            ->with('success', $user->name.' has been updated.');
    }

    /**
     * Enable / disable a staff account instead of deleting it.
     */
    public function toggle(User $user): RedirectResponse
    {
        $this->guardLastAdmin($user, ! $user->is_active, $user->roles->first()?->name ?? '');

        $user->is_active = ! $user->is_active;
        $user->save();

        activity('staff')->performedOn($user)->causedBy(auth('admin')->user())
            ->log($user->is_active ? 'Enabled staff account' : 'Disabled staff account');

        return back()->with('success', $user->name.' is now '.($user->is_active ? 'active' : 'disabled').'.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth('admin')->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $this->guardLastAdmin($user, false, '');

        $name = $user->name;
        $user->delete(); // soft delete

        activity('staff')->performedOn($user)->causedBy(auth('admin')->user())->log('Deleted staff account');

        return redirect()->route('admin.users.index')->with('success', $name.' has been removed.');
    }

    /**
     * Never allow the last active Admin to be disabled, demoted or deleted.
     */
    protected function guardLastAdmin(User $user, bool $willBeActive, string $newRole): void
    {
        if (! $user->hasRole('Admin')) {
            return;
        }

        $stillAdmin = $willBeActive && $newRole === 'Admin';

        if ($stillAdmin) {
            return;
        }

        $otherAdmins = User::query()
            ->active()
            ->whereKeyNot($user->id)
            ->whereHas('roles', fn ($q) => $q->where('name', 'Admin'))
            ->count();

        if ($otherAdmins === 0) {
            throw ValidationException::withMessages([
                'role' => 'At least one active Admin must remain. Assign the Admin role to another user first.',
            ]);
        }
    }

    protected function roles()
    {
        return Role::query()->where('guard_name', 'admin')->orderBy('name')->pluck('name');
    }
}
