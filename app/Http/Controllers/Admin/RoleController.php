<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Support\ChatPermissions;
use App\Support\ReportPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::withCount(['permissions', 'users'])->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.create', $this->permissionOptions());
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::create(['name' => $request->validated('name'), 'guard_name' => 'web']);

        $role->syncPermissions($this->permissionsFrom($request->validated()));

        return redirect()->route('admin.roles.index')->with('status', "Role \"{$role->name}\" berhasil dibuat.");
    }

    public function edit(Role $role): View
    {
        $this->guardProtectedRole($role);

        return view('admin.roles.edit', [
            ...$this->permissionOptions(),
            'role' => $role,
            'rolePermissions' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->guardProtectedRole($role);

        $role->update(['name' => $request->validated('name')]);
        $role->syncPermissions($this->permissionsFrom($request->validated()));

        return redirect()->route('admin.roles.index')->with('status', "Role \"{$role->name}\" berhasil diperbarui.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->guardProtectedRole($role);

        if ($role->users()->count() > 0) {
            return back()->with('error', "Role \"{$role->name}\" masih dipakai oleh ".$role->users()->count().' pengguna — pindahkan mereka ke role lain dulu.');
        }

        $role->delete();

        return redirect()->route('admin.roles.index')->with('status', "Role \"{$role->name}\" berhasil dihapus.");
    }

    /**
     * "superuser" is fixed/protected — not editable or deletable through this UI,
     * since a large part of the app's authorization (ReportPolicy::before(), the
     * locked-report override, reporter identity reveal) hardcodes that exact name.
     */
    private function guardProtectedRole(Role $role): void
    {
        abort_if($role->name === 'superuser', 403, 'Role Superuser tidak bisa diubah lewat halaman ini.');
    }

    private function permissionOptions(): array
    {
        return [
            'abilities' => ReportPermissions::ABILITIES,
            'chatAbilities' => ChatPermissions::ABILITIES,
            'assignedOnlyPermission' => ReportPermissions::ASSIGNED_ONLY,
            'statusLabels' => \App\Models\Report::STATUS_LABELS,
        ];
    }

    /**
     * @return array<int,string>
     */
    private function permissionsFrom(array $validated): array
    {
        $statusPermissions = array_map(
            fn (string $status) => ReportPermissions::statusPermission($status),
            $validated['statuses'] ?? []
        );

        return [
            ...($validated['abilities'] ?? []),
            ...(($validated['assigned_only'] ?? false) ? [ReportPermissions::ASSIGNED_ONLY] : []),
            ...$statusPermissions,
        ];
    }
}
