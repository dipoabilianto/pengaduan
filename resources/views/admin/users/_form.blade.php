@php
    $user = $user ?? null;
    $currentRole = $user?->roles->first()?->name;
@endphp

<div>
    <label class="block text-sm font-medium text-gray-700">Nama</label>
    <input type="text" name="name" value="{{ old('name', $user->name ?? '') }}" required class="mt-1 w-full rounded-md border-gray-300 text-sm">
    @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-gray-700">Email</label>
    <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" required class="mt-1 w-full rounded-md border-gray-300 text-sm">
    @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label class="block text-sm font-medium text-gray-700">Password</label>
        <input type="password" name="password" autocomplete="new-password" {{ $user ? '' : 'required' }} class="mt-1 w-full rounded-md border-gray-300 text-sm">
        <p class="mt-1 text-xs text-gray-400">{{ $user ? 'Kosongkan jika tidak ingin mengubah password.' : 'Minimal 8 karakter.' }}</p>
        @error('password')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">Konfirmasi Password</label>
        <input type="password" name="password_confirmation" autocomplete="new-password" class="mt-1 w-full rounded-md border-gray-300 text-sm">
    </div>
</div>

<div>
    <label class="block text-sm font-medium text-gray-700">Role</label>
    <select name="role" required class="mt-1 w-full rounded-md border-gray-300 text-sm">
        <option value="">— Pilih role —</option>
        @foreach ($roles as $roleOption)
            <option value="{{ $roleOption->name }}" @selected(old('role', $currentRole) === $roleOption->name)>
                {{ $roleOption->name === 'superuser' ? 'Super User' : $roleOption->name }}
            </option>
        @endforeach
    </select>
    <p class="mt-1 text-xs text-gray-400">Menentukan status laporan mana yang bisa dilihat pengguna ini — atur lewat halaman Peran & Izin.</p>
    @error('role')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
</div>
