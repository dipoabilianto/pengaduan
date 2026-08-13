<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pengaturan Notifikasi Push</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Push Notification (Firebase Cloud Messaging)</h3>
                    <span @class([
                        'rounded-full px-3 py-1 text-xs font-medium',
                        'bg-emerald-100 text-emerald-700' => $isConfigured,
                        'bg-amber-100 text-amber-700' => ! $isConfigured,
                    ])>
                        {{ $isConfigured ? 'Terkonfigurasi' : 'Belum Dikonfigurasi' }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500">
                    Digunakan untuk Fase 6.1 — notifikasi ke aplikasi Android Admin/Pejabat (laporan baru, eskalasi,
                    Red Code, ringkasan harian). Kredensial service account disimpan terenkripsi dan hanya dapat
                    diubah oleh Superuser.
                </p>

                <form method="POST" action="{{ route('admin.settings.push.update') }}" class="mt-6 space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Firebase Project ID</label>
                        <input type="text" name="project_id" value="{{ old('project_id', $projectId) }}" placeholder="mis. sidumas-tubaba"
                               class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm">
                        @error('project_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Service Account JSON</label>
                        <textarea name="service_account_json" rows="6" autocomplete="off"
                                  placeholder="{{ $hasServiceAccount ? 'Tersimpan — kosongkan jika tidak ingin mengubah' : 'Tempel isi file JSON service account Firebase di sini' }}"
                                  class="mt-1 w-full rounded-md border-gray-300 font-mono text-xs">{{ old('service_account_json') }}</textarea>
                        <p class="mt-1 text-xs text-gray-400">
                            @if ($hasServiceAccount)
                                Service account saat ini sudah tersimpan. Isi field ini hanya jika ingin menggantinya.
                            @else
                                Belum ada service account tersimpan. Unduh dari Firebase Console &rarr; Project Settings &rarr; Service Accounts &rarr; Generate new private key.
                            @endif
                        </p>
                        @error('service_account_json')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <button type="submit" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">
                        Simpan Pengaturan
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
