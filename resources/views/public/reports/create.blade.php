<x-layouts.public :title="'Buat Pengaduan — KATA-KITA'">
    <section class="mx-auto max-w-2xl px-6 py-16">
        <h1 class="text-center text-2xl font-bold sm:text-3xl">Formulir Pengaduan &amp; Whistleblowing</h1>
        <p class="mt-2 text-center text-sm text-slate-300">
            Data Anda dilindungi &amp; dienkripsi. Nomor HP hanya digunakan sebagai identitas pelaporan (PK).
        </p>

        @if ($errors->any())
            <div class="mt-6 rounded-xl border border-rose-400/30 bg-rose-500/10 p-4 text-sm text-rose-200">
                <p class="font-semibold">Periksa kembali isian Anda:</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            // Setelah validasi gagal (mis. CAPTCHA salah) & halaman reload, wizard harus
            // kembali ke tahap yang bermasalah — bukan selalu ke tahap 1 — supaya pelapor
            // tidak perlu klik "Lanjut" ulang padahal isian sebelumnya sudah tersimpan via old().
            $stepFields = [
                1 => ['type', 'category', 'reported_party'],
                2 => ['name', 'phone'],
                3 => ['what', 'who', 'where', 'when', 'how', 'why'],
                4 => ['attachments'],
                5 => ['captcha'],
            ];
            $initialStep = 1;
            foreach ($stepFields as $stepNumber => $fields) {
                // 'attachments' errors come back keyed as attachments.0, attachments.1, ... —
                // match on the field prefix, not just an exact key.
                $hasError = collect($errors->keys())->contains(
                    fn ($key) => collect($fields)->contains(fn ($field) => $key === $field || str_starts_with($key, $field.'.'))
                );

                if ($hasError) {
                    $initialStep = $stepNumber;
                    break;
                }
            }
        @endphp

        <div
            x-data="reportWizard()"
            class="mt-8 rounded-2xl border border-white/10 bg-white/5 p-6 shadow-2xl backdrop-blur-lg sm:p-8"
        >
            {{-- Progress indicator --}}
            <ol class="mb-8 flex items-center justify-between text-xs text-slate-400">
                <template x-for="n in 5" :key="n">
                    <li class="flex flex-1 items-center" :class="n < 5 ? 'after:mx-2 after:h-px after:flex-1 after:bg-white/10 after:content-[\'\']' : ''">
                        <span
                            class="flex h-7 w-7 items-center justify-center rounded-full border text-[11px] font-semibold"
                            :class="step >= n ? 'border-sky-400 bg-sky-500 text-white' : 'border-white/20 text-slate-400'"
                            x-text="n"
                        ></span>
                    </li>
                </template>
            </ol>

            <form method="POST" action="{{ route('report.store') }}" enctype="multipart/form-data" @submit="if(!canNext(5)) $event.preventDefault()">
                @csrf

                {{-- Step 1: Jenis Pelaporan --}}
                <div x-show="step === 1" x-cloak>
                    <h2 class="text-lg font-semibold">1. Pilih Jenis Pelaporan</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label class="cursor-pointer rounded-xl border p-4 transition"
                               :class="form.type === 'pengaduan' ? 'border-sky-400 bg-sky-500/10' : 'border-white/15 bg-white/5'">
                            <input type="radio" name="type" value="pengaduan" x-model="form.type" @change="form.category = ''" class="sr-only">
                            <span class="block font-medium">Pengaduan Pelayanan Publik</span>
                            <span class="mt-1 block text-xs text-slate-300">Keluhan terkait kualitas/proses layanan Disdukcapil.</span>
                        </label>
                        <label class="cursor-pointer rounded-xl border p-4 transition"
                               :class="form.type === 'whistleblowing' ? 'border-sky-400 bg-sky-500/10' : 'border-white/15 bg-white/5'">
                            <input type="radio" name="type" value="whistleblowing" x-model="form.type" @change="form.category = ''" class="sr-only">
                            <span class="block font-medium">Whistleblowing</span>
                            <span class="mt-1 block text-xs text-slate-300">Dugaan pelanggaran, gratifikasi, pungli, atau maladministrasi.</span>
                        </label>
                    </div>

                    <label class="mt-6 block text-sm font-medium text-slate-200">Kategori</label>
                    <select name="category" x-model="form.category" :disabled="!form.type" class="mt-1 w-full rounded-lg border-white/15 bg-white/10 text-slate-100 focus:border-sky-400 focus:ring-sky-400 disabled:opacity-50">
                        <option value="" class="text-slate-900">— Pilih jenis pelaporan dulu —</option>
                        <template x-for="option in categoriesFor(form.type)" :key="option">
                            <option :value="option" x-text="option" class="text-slate-900"></option>
                        </template>
                    </select>

                    <template x-if="form.type === 'whistleblowing'">
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-slate-200">Pihak yang Dilaporkan (opsional)</label>
                            <input type="text" name="reported_party" x-model="form.reported_party"
                                   class="mt-1 w-full rounded-lg border-white/15 bg-white/10 text-slate-100 placeholder-slate-400 focus:border-sky-400 focus:ring-sky-400"
                                   placeholder="Nama/jabatan pihak yang diduga terlibat (jika diketahui)">
                            <p class="mt-1 text-xs text-slate-400">Boleh dikosongkan jika belum mengetahui identitas pastinya.</p>
                        </div>
                    </template>
                </div>

                {{-- Step 2: Data Pelapor --}}
                <div x-show="step === 2" x-cloak>
                    <h2 class="text-lg font-semibold">2. Data Pelapor</h2>
                    <p class="mt-1 text-xs text-slate-400">Nama bersifat opsional — Anda dapat melapor secara anonim.</p>

                    <label class="mt-4 block text-sm font-medium text-slate-200">Nama (opsional)</label>
                    <input type="text" name="name" x-model="form.name" class="mt-1 w-full rounded-lg border-white/15 bg-white/10 text-slate-100 placeholder-slate-400 focus:border-sky-400 focus:ring-sky-400" placeholder="Nama lengkap">

                    <label class="mt-4 block text-sm font-medium text-slate-200">Nomor HP/WhatsApp Aktif (PK) <span class="text-rose-300">*</span></label>
                    <input type="tel" name="phone" x-model="form.phone" required class="mt-1 w-full rounded-lg border-white/15 bg-white/10 text-slate-100 placeholder-slate-400 focus:border-sky-400 focus:ring-sky-400" placeholder="08xxxxxxxxxx">
                    <p class="mt-1 text-xs text-slate-400">Simpan nomor ini — digunakan untuk cek status laporan Anda.</p>
                </div>

                {{-- Step 3: 5W1H --}}
                <div x-show="step === 3" x-cloak class="space-y-4">
                    <h2 class="text-lg font-semibold">3. Detail Kejadian (5W1H)</h2>

                    <div>
                        <label class="block text-sm font-medium text-slate-200">Apa yang terjadi? (What) <span class="text-rose-300">*</span></label>
                        <textarea name="what" x-model="form.what" required rows="3" class="mt-1 w-full rounded-lg border-white/15 bg-white/10 text-slate-100 placeholder-slate-400 focus:border-sky-400 focus:ring-sky-400"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-200">Siapa yang terlibat? (Who)</label>
                        <textarea name="who" x-model="form.who" rows="2" class="mt-1 w-full rounded-lg border-white/15 bg-white/10 text-slate-100 placeholder-slate-400 focus:border-sky-400 focus:ring-sky-400"></textarea>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-200">Lokasi (Where)</label>
                            <input type="text" name="where" x-model="form.where" class="mt-1 w-full rounded-lg border-white/15 bg-white/10 text-slate-100 focus:border-sky-400 focus:ring-sky-400">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-200">Waktu Kejadian (When)</label>
                            <input type="datetime-local" name="when" x-model="form.when" class="mt-1 w-full rounded-lg border-white/15 bg-white/10 text-slate-100 focus:border-sky-400 focus:ring-sky-400">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-200">Bagaimana kejadian berlangsung? (How)</label>
                        <textarea name="how" x-model="form.how" rows="2" class="mt-1 w-full rounded-lg border-white/15 bg-white/10 text-slate-100 focus:border-sky-400 focus:ring-sky-400"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-200">Mengapa hal ini dilaporkan? (Why)</label>
                        <textarea name="why" x-model="form.why" rows="2" class="mt-1 w-full rounded-lg border-white/15 bg-white/10 text-slate-100 focus:border-sky-400 focus:ring-sky-400"></textarea>
                    </div>
                </div>

                {{-- Step 4: Upload Bukti --}}
                <div x-show="step === 4" x-cloak>
                    <h2 class="text-lg font-semibold">4. Upload Bukti (opsional)</h2>
                    <p class="mt-1 text-xs text-slate-400">Format JPG/PNG/PDF, maks. 5MB per file, maks. 5 file.</p>
                    <input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf" class="mt-4 w-full rounded-lg border border-dashed border-white/20 bg-white/5 p-4 text-sm text-slate-300 file:mr-4 file:rounded-lg file:border-0 file:bg-sky-500 file:px-3 file:py-1.5 file:text-white">
                </div>

                {{-- Step 5: CAPTCHA — kode dikirim sebagai teks (bukan gambar), dirender
                     sebagai HTML biasa supaya selalu tajam & besar di layar manapun. --}}
                <div x-show="step === 5" x-cloak>
                    <h2 class="text-lg font-semibold">5. Verifikasi</h2>
                    <p class="mt-1 text-xs text-slate-400">Ketik ulang kode di bawah ini.</p>
                    <div class="mt-4 flex items-center gap-3">
                        <div class="flex h-[70px] min-w-[200px] items-center justify-center rounded-lg border border-white/15 bg-white/5 px-6">
                            <span class="font-mono text-4xl font-bold tracking-[0.35em] text-slate-100" x-text="captchaCode || '…'"></span>
                        </div>
                        <button type="button" @click="refreshCaptcha()" class="rounded-lg border border-white/20 px-3 py-2 text-xs text-slate-200 hover:bg-white/10">
                            Ganti Kode
                        </button>
                    </div>
                    <label class="mt-4 block text-sm font-medium text-slate-200">Masukkan kode di atas <span class="text-rose-300">*</span></label>
                    <input type="text" name="captcha" x-model="form.captcha" required maxlength="5" class="mt-1 w-40 rounded-lg border-white/15 bg-white/10 text-2xl uppercase tracking-widest text-slate-100 focus:border-sky-400 focus:ring-sky-400">
                </div>

                {{-- Navigation --}}
                <div class="mt-8 flex items-center justify-between">
                    <button type="button" x-show="step > 1" x-cloak @click="step--" class="rounded-lg border border-white/20 px-4 py-2 text-sm text-slate-200 hover:bg-white/10">
                        Kembali
                    </button>
                    <span x-show="step === 1"></span>

                    <button type="button" x-show="step < 5" x-cloak @click="if (canNext(step)) step++" class="ml-auto rounded-lg bg-sky-500 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-sky-500/30 hover:bg-sky-400">
                        Lanjut
                    </button>
                    <button type="submit" x-show="step === 5" x-cloak class="ml-auto rounded-lg bg-emerald-500 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-emerald-500/30 hover:bg-emerald-400">
                        Kirim Laporan
                    </button>
                </div>
            </form>
        </div>
    </section>

    <script>
        function reportWizard() {
            return {
                step: {{ $initialStep }},
                categoriesByType: {
                    pengaduan: @json(\App\Models\Report::CATEGORIES_PENGADUAN),
                    whistleblowing: @json(\App\Models\Report::CATEGORIES_WHISTLEBLOWING),
                },
                categoriesFor(type) {
                    return this.categoriesByType[type] ?? [];
                },
                form: {
                    type: '{{ old('type', '') }}',
                    category: '{{ old('category', '') }}',
                    reported_party: '{{ old('reported_party', '') }}',
                    name: '{{ old('name', '') }}',
                    phone: '{{ old('phone', '') }}',
                    what: '{{ old('what', '') }}',
                    who: '{{ old('who', '') }}',
                    where: '{{ old('where', '') }}',
                    when: '{{ old('when', '') }}',
                    how: '{{ old('how', '') }}',
                    why: '{{ old('why', '') }}',
                    captcha: '',
                },
                captchaCode: '',
                init() {
                    this.refreshCaptcha();
                },
                refreshCaptcha() {
                    fetch('{{ route('captcha') }}')
                        .then((response) => response.json())
                        .then((data) => { this.captchaCode = data.code; });
                },
                canNext(currentStep) {
                    if (currentStep === 1) return this.form.type && this.form.category;
                    if (currentStep === 2) return this.form.phone;
                    if (currentStep === 3) return this.form.what;
                    if (currentStep === 5) return this.form.captcha;
                    return true;
                },
            };
        }
    </script>
</x-layouts.public>
