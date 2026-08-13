<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pengaturan Instagram</h2>
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
                    <h3 class="text-lg font-semibold">Inbox Terpadu — Instagram (Graph API)</h3>
                    <span @class([
                        'rounded-full px-3 py-1 text-xs font-medium',
                        'bg-emerald-100 text-emerald-700' => $isConfigured,
                        'bg-amber-100 text-amber-700' => ! $isConfigured,
                    ])>
                        {{ $isConfigured ? 'Terkonfigurasi' : 'Belum Dikonfigurasi' }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500">
                    Menerima DM, komentar, dan mention dari akun Instagram Professional ke Inbox Terpadu, serta
                    mengirim balasan langsung dari Sidumas. Menggunakan Instagram Graph API resmi dari Meta for
                    Developers (akun Instagram harus terhubung ke Facebook Page). Token akses disimpan terenkripsi
                    dan hanya dapat diubah oleh Superuser.
                </p>

                <form method="POST" action="{{ route('admin.settings.instagram.update') }}" class="mt-6 space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Instagram Business Account ID</label>
                        <input type="text" name="business_account_id" value="{{ old('business_account_id', $businessAccountId) }}" placeholder="mis. 17841400000000000"
                               class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm">
                        @error('business_account_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Access Token</label>
                        <input type="password" name="access_token" autocomplete="off"
                               placeholder="{{ $maskedAccessToken ? 'Tersimpan: '.$maskedAccessToken.' — kosongkan jika tidak ingin mengubah' : 'Masukkan access token' }}"
                               class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm">
                        <p class="mt-1 text-xs text-gray-400">
                            @if ($maskedAccessToken)
                                Access token saat ini tersimpan ({{ $maskedAccessToken }}). Isi field ini hanya jika ingin menggantinya.
                            @else
                                Belum ada access token tersimpan.
                            @endif
                        </p>
                        @error('access_token')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Webhook Verify Token</label>
                        <input type="text" name="webhook_verify_token" value="{{ old('webhook_verify_token', $webhookVerifyToken) }}" placeholder="Bebas dipilih, mis. token acak"
                               class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm">
                        <p class="mt-1 text-xs text-gray-400">Tempelkan nilai yang sama di kolom "Verify Token" pada pengaturan Webhook Instagram di Meta App Dashboard.</p>
                        @error('webhook_verify_token')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">URL Webhook</label>
                        <input type="text" value="{{ url('/api/webhooks/instagram') }}" readonly
                               class="mt-1 w-full rounded-md border-gray-200 bg-gray-50 font-mono text-sm text-gray-500">
                        <p class="mt-1 text-xs text-gray-400">Tempelkan URL ini di kolom "Callback URL" pada pengaturan Webhook Instagram di Meta App Dashboard. Aktifkan subscription "messages", "comments", dan "mentions".</p>
                    </div>

                    <button type="submit" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">
                        Simpan Pengaturan
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
