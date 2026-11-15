<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller implements HasMiddleware
{
    /** Roles created by the seeder that must not be renamed or removed. */
    protected array $systemRoles = ['Admin', 'Manager', 'Sales', 'Inventory', 'Delivery'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:roles.view', only: ['index']),
            new Middleware('permission:roles.create', only: ['create', 'store']),
            new Middleware('permission:roles.edit', only: ['edit', 'update']),
            new Middleware('permission:roles.delete', only: ['destroy']),
        ];
    }

    public function index(): View
    {
        $roles = Role::query()
            ->where('guard_name', 'admin')
            ->withCount(['permissions', 'users'])
            ->orderBy('id')
            ->get();

        return view('admin.roles.index', [
            'roles' => $roles,
            'systemRoles' => $this->systemRoles,
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.form', [
            'role' => new Role(['guard_name' => 'admin']),
            'grouped' => $this->groupedPermissions(),
            'assigned' => [],
            'systemRoles' => $this->systemRoles,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateRole($request);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'admin']);
        $role->syncPermissions($data['permissions'] ?? []);

        activity('roles')->performedOn($role)->causedBy(auth('admin')->user())->log('Created role');

        return redirect()->route('admin.roles.index')->with('success', 'Role "'.$role->name.'" created.');
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.form', [
            'role' => $role,
            'grouped' => $this->groupedPermissions(),
            'assigned' => $role->permissions->pluck('name')->all(),
            'systemRoles' => $this->systemRoles,
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $this->validateRole($request, $role);

        if ($role->name === 'Admin') {
            throw ValidationException::withMessages([
                'name' => 'The Admin role always keeps every permission and cannot be edited.',
            ]);
        }

        if (! in_array($role->name, $this->systemRoles, true)) {
            $role->name = $data['name'];
            $role->save();
        }

        $role->syncPermissions($data['permissions'] ?? []);

        activity('roles')->performedOn($role)->causedBy(auth('admin')->user())->log('Updated role permissions');

        return redirect()->route('admin.roles.index')->with('success', 'Role "'.$role->name.'" updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if (in_array($role->name, $this->systemRoles, true)) {
            return back()->with('error', 'System roles cannot be deleted.');
        }

        if ($role->users()->count() > 0) {
            return back()->with('error', 'Move the staff on this role to another role first.');
        }

        $name = $role->name;
        $role->delete();

        activity('roles')->causedBy(auth('admin')->user())->log('Deleted role '.$name);

        return redirect()->route('admin.roles.index')->with('success', 'Role "'.$name.'" deleted.');
    }

    protected function validateRole(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')->where('guard_name', 'admin')->ignore($role?->id)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'admin')],
        ]);
    }

    /**
     * Permissions grouped by module for the checkbox matrix.
     *
     * @return array<string, array<string, string>>
     */
    protected function groupedPermissions(): array
    {
        $labels = [
            'view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete',
        ];

        $grouped = [];

        foreach (Permission::query()->where('guard_name', 'admin')->orderBy('id')->pluck('name') as $name) {
            [$module, $action] = array_pad(explode('.', $name, 2), 2, 'view');
            $grouped[$module][$name] = $labels[$action] ?? ucfirst($action);
        }

        // Keep the seeder's module order.
        $ordered = [];
        foreach (array_keys(RolePermissionSeeder::MODULES) as $module) {
            if (isset($grouped[$module])) {
                $ordered[$module] = $grouped[$module];
                unset($grouped[$module]);
            }
        }

        return $ordered + $grouped;
    }
}
