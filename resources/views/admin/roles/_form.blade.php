@php
    $role = $role ?? null;
    $rolePermissions = $rolePermissions ?? [];

    $defaultAbilities = array_values(array_intersect($rolePermissions, array_keys($abilities)));
    $defaultChatAbilities = array_values(array_intersect($rolePermissions, array_keys($chatAbilities)));
    $defaultAssignedOnly = in_array($assignedOnlyPermission, $rolePermissions, true);
    $defaultStatuses = collect($statusLabels)
        ->keys()
        ->filter(fn ($status) => in_array(\App\Support\ReportPermissions::statusPermission($status), $rolePermissions, true))
        ->values()
        ->all();
@endphp

<div>
    <label class="block text-sm font-medium text-gray-700">Nama Role</label>
    <input type="text" name="name" value="{{ old('name', $role->name ?? '') }}" required placeholder="mis. Pelaksana Lapangan" class="mt-1 w-full max-w-sm rounded-md border-gray-300 text-sm">
    @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
</div>

<div>
    <h3 class="text-sm font-semibold text-gray-700">Kemampuan</h3>
    <p class="mt-0.5 text-xs text-gray-400">Apa saja yang boleh dilakukan pengguna dengan role ini terhadap laporan yang bisa mereka lihat.</p>
    <div class="mt-2 space-y-2">
        @foreach ($abilities as $permission => $label)
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="abilities[]" value="{{ $permission }}" @checked(in_array($permission, old('abilities', $defaultAbilities))) class="rounded border-gray-300 text-sky-600 focus:ring-sky-500">
                {{ $label }}
            </label>
        @endforeach
    </div>
</div>

<div>
    <h3 class="text-sm font-semibold text-gray-700">Live Chat</h3>
    <p class="mt-0.5 text-xs text-gray-400">Akses ke kotak masuk chat pelapor — terpisah dari izin laporan di atas.</p>
    <div class="mt-2 space-y-2">
        @foreach ($chatAbilities as $permission => $label)
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="abilities[]" value="{{ $permission }}" @checked(in_array($permission, old('abilities', $defaultChatAbilities))) class="rounded border-gray-300 text-sky-600 focus:ring-sky-500">
                {{ $label }}
            </label>
        @endforeach
    </div>
</div>

<div>
    <h3 class="text-sm font-semibold text-gray-700">Batasan</h3>
    <label class="mt-2 flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" name="assigned_only" value="1" @checked(old('assigned_only', $defaultAssignedOnly)) class="rounded border-gray-300 text-sky-600 focus:ring-sky-500">
        Hanya laporan yang di-assign ke diri sendiri
    </label>
    <p class="mt-1 text-xs text-gray-400">Kalau dicentang, pengguna dengan role ini cuma lihat laporan yang memang ditugaskan/diteruskan ke dirinya — bukan seluruh laporan di status yang diizinkan di bawah.</p>
</div>

<div>
    <h3 class="text-sm font-semibold text-gray-700">Status Laporan yang Boleh Dilihat</h3>
    <p class="mt-0.5 text-xs text-gray-400">Kosongkan semua kalau role ini memang belum boleh melihat laporan apa pun.</p>
    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
        @foreach ($statusLabels as $status => $label)
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="statuses[]" value="{{ $status }}" @checked(in_array($status, old('statuses', $defaultStatuses))) class="rounded border-gray-300 text-sky-600 focus:ring-sky-500">
                {{ $label }}
            </label>
        @endforeach
    </div>
</div>
