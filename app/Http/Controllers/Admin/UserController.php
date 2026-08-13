<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::with('roles')->orderBy('name')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'email_verified_at' => now(),
        ]);

        $user->syncRoles([$request->validated('role')]);

        return redirect()->route('admin.users.index')->with('status', "Pengguna \"{$user->name}\" berhasil dibuat.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->update(array_filter([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
        ], fn ($value) => $value !== null));

        $user->syncRoles([$request->validated('role')]);

        return back()->with('status', "Pengguna \"{$user->name}\" berhasil diperbarui.");
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak bisa menghapus akun Anda sendiri.');
        }

        if ($user->hasRole('superuser') && User::role('superuser')->count() <= 1) {
            return back()->with('error', 'Tidak bisa menghapus Superuser terakhir yang tersisa.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', "Pengguna \"{$user->name}\" berhasil dihapus.");
    }
}
