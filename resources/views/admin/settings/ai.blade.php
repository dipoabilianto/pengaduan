<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pengaturan Integrasi AI</h2>
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
                    <h3 class="text-lg font-semibold">Penilaian Urgensi Berbasis AI</h3>
                    <span @class([
                        'rounded-full px-3 py-1 text-xs font-medium',
                        'bg-emerald-100 text-emerald-700' => $isConfigured,
                        'bg-amber-100 text-amber-700' => ! $isConfigured,
                    ])>
                        {{ $isConfigured ? 'Terkonfigurasi' : 'Belum Dikonfigurasi' }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500">
                    Digunakan untuk Fase 4 — analisis teks 5W1H &amp; rekomendasi tingkat urgensi laporan (Bab 5.3 PDR).
                    Kunci API disimpan terenkripsi dan hanya dapat diubah oleh Superuser.
                </p>

                <form method="POST" action="{{ route('admin.settings.ai.update') }}" class="mt-6 space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Penyedia AI</label>
                        <select name="provider" required class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            <option value="">— Pilih penyedia —</option>
                            @foreach (\App\Services\AiSettingsService::PROVIDERS as $value => $label)
                                <option value="{{ $value }}" @selected($provider === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('provider')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Model (opsional)</label>
                        <input type="text" name="model" value="{{ old('model', $model) }}" placeholder="mis. claude-sonnet-5, gpt-4o, gemini-flash-lite-latest, openai/gpt-oss-120b (Groq)"
                               class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        @error('model')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">API Key</label>
                        <input type="password" name="api_key" autocomplete="off"
                               placeholder="{{ $maskedApiKey ? 'Tersimpan: '.$maskedApiKey.' — kosongkan jika tidak ingin mengubah' : 'Masukkan API key' }}"
                               class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm">
                        <p class="mt-1 text-xs text-gray-400">
                            @if ($maskedApiKey)
                                API key saat ini tersimpan ({{ $maskedApiKey }}). Isi field ini hanya jika ingin menggantinya.
                            @else
                                Belum ada API key tersimpan.
                            @endif
                        </p>
                        @error('api_key')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <button type="submit" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">
                        Simpan Pengaturan
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
