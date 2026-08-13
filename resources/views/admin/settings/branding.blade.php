<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pengaturan Logo &amp; Favicon</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">Identitas Visual Sistem</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Logo tampil di navigasi &amp; halaman publik, favicon tampil di tab browser. Ganti kapan saja
                    tanpa perlu mengubah kode — file lama otomatis digantikan.
                </p>

                <form method="POST" action="{{ route('admin.settings.branding.update') }}" class="mt-6 space-y-6" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Logo</label>
                        <div class="mt-2 flex items-center gap-4">
                            <span class="flex h-16 max-w-[10rem] shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50 px-2">
                                @if ($logoUrl)
                                    <img src="{{ $logoUrl }}" alt="Logo saat ini" class="h-full w-auto object-contain">
                                @else
                                    <x-application-logo class="h-9 w-auto fill-current text-gray-400" />
                                @endif
                            </span>
                            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-sky-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-sky-700 hover:file:bg-sky-100">
                        </div>
                        <p class="mt-1 text-xs text-gray-400">PNG, JPG, WEBP, atau SVG. Maks. 2 MB. Ukuran asli dipertahankan (tidak dipotong) di mana pun logo ditampilkan.</p>
                        @error('logo')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Favicon</label>
                        <div class="mt-2 flex items-center gap-4">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
                                @if ($faviconUrl)
                                    <img src="{{ $faviconUrl }}" alt="Favicon saat ini" class="h-full w-full object-contain">
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </span>
                            <input type="file" name="favicon" accept="image/png,image/jpeg,image/webp,image/gif,image/bmp" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-sky-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-sky-700 hover:file:bg-sky-100">
                        </div>
                        <p class="mt-1 text-xs text-gray-400">PNG, JPG, WEBP, GIF, atau BMP. Maks. 2 MB. Otomatis dipaskan ke kanvas persegi dengan latar transparan — tidak akan terpotong atau berkotak putih di tab browser.</p>
                        @error('favicon')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <button type="submit" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">
                        Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
