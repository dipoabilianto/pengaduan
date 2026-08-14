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
                5 => ['g-recaptcha-response'],
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
                    <li class="flex flex-1 items-center" :class="n < 5 ? (step > n ? 'after:mx-2 after:h-px after:flex-1 after:bg-sky-400/60 after:transition-colors after:duration-300 after:content-[\'\']' : 'after:mx-2 after:h-px after:flex-1 after:bg-white/10 after:transition-colors after:duration-300 after:content-[\'\']') : ''">
                        <span
                            class="flex h-7 w-7 items-center justify-center rounded-full border text-[11px] font-semibold transition-all duration-300"
                            :class="step >= n ? 'border-sky-400 bg-sky-500 text-white scale-110' : 'border-white/20 text-slate-400'"
                            x-text="n"
                        ></span>
                    </li>
                </template>
            </ol>

            <form method="POST" action="{{ route('report.store') }}" enctype="multipart/form-data" @submit="if(!canNext(5)) $event.preventDefault()">
                @csrf

                {{-- Step 1: Jenis Pelaporan --}}
                <div x-show="step === 1" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-3" x-transition:enter-end="opacity-100 translate-x-0">
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
                <div x-show="step === 2" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-3" x-transition:enter-end="opacity-100 translate-x-0">
                    <h2 class="text-lg font-semibold">2. Data Pelapor</h2>
                    <p class="mt-1 text-xs text-slate-400">Nama bersifat opsional — Anda dapat melapor secara anonim.</p>

                    <label class="mt-4 block text-sm font-medium text-slate-200">
                        Nomor HP/WhatsApp Aktif (PK)
                        <span class="ml-1 rounded-full bg-rose-500/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-rose-300">Wajib Diisi</span>
                    </label>
                    <input type="tel" name="phone" x-model="form.phone" required class="mt-1 w-full rounded-lg border-white/15 bg-white/10 text-slate-100 placeholder-slate-400 focus:border-sky-400 focus:ring-sky-400" placeholder="08xxxxxxxxxx">
                    <p class="mt-1 text-xs text-slate-400">Simpan nomor ini — digunakan untuk cek status laporan Anda.</p>

                    <label class="mt-6 block text-sm font-medium text-slate-200">
                        Nama
                        <span class="ml-1 rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-300">Tidak Wajib</span>
                    </label>
                    <input type="text" name="name" x-model="form.name" class="mt-1 w-full rounded-lg border-white/15 bg-white/10 text-slate-100 placeholder-slate-400 focus:border-sky-400 focus:ring-sky-400" placeholder="Nama lengkap">
                </div>

                {{-- Step 3: 5W1H --}}
                <div x-show="step === 3" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-3" x-transition:enter-end="opacity-100 translate-x-0" class="space-y-4">
                    <h2 class="text-lg font-semibold">3. Detail Kejadian (5W1H)</h2>

                    <div>
                        <label class="block text-sm font-medium text-slate-200">
                            Apa yang terjadi? (What)
                            <span class="ml-1 rounded-full bg-rose-500/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-rose-300">Wajib Diisi</span>
                        </label>
                        <textarea name="what" x-model="form.what" required rows="3" class="mt-1 w-full rounded-lg border-white/15 bg-white/10 text-slate-100 placeholder-slate-400 focus:border-sky-400 focus:ring-sky-400"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-200">
                            Siapa yang terlibat? (Who)
                            <span class="ml-1 rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-300">Tidak Wajib Diisi</span>
                        </label>
                        <textarea name="who" x-model="form.who" rows="2" class="mt-1 w-full rounded-lg border-white/15 bg-white/10 text-slate-100 placeholder-slate-400 focus:border-sky-400 focus:ring-sky-400"></textarea>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-200">
                                Lokasi (Where)
                                <span class="ml-1 rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-300">Tidak Wajib</span>
                            </label>
                            <input type="text" name="where" x-model="form.where" class="mt-1 w-full rounded-lg border-white/15 bg-white/10 text-slate-100 focus:border-sky-400 focus:ring-sky-400">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-200">
                                Waktu Kejadian (When)
                                <span class="ml-1 rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-300">Tidak Wajib</span>
                            </label>
                            <input type="datetime-local" name="when" x-model="form.when" class="mt-1 w-full rounded-lg border-white/15 bg-white/10 text-slate-100 focus:border-sky-400 focus:ring-sky-400">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-200">
                            Bagaimana kejadian berlangsung? (How)
                            <span class="ml-1 rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-300">Tidak Wajib Diisi</span>
                        </label>
                        <textarea name="how" x-model="form.how" rows="2" class="mt-1 w-full rounded-lg border-white/15 bg-white/10 text-slate-100 focus:border-sky-400 focus:ring-sky-400"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-200">
                            Mengapa hal ini dilaporkan? (Why)
                            <span class="ml-1 rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-300">Tidak Wajib Diisi</span>
                        </label>
                        <textarea name="why" x-model="form.why" rows="2" class="mt-1 w-full rounded-lg border-white/15 bg-white/10 text-slate-100 focus:border-sky-400 focus:ring-sky-400"></textarea>
                    </div>
                </div>

                {{-- Step 4: Upload Bukti --}}
                <div x-show="step === 4" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-3" x-transition:enter-end="opacity-100 translate-x-0">
                    <h2 class="text-lg font-semibold">
                        4. Upload Bukti
                        <span class="ml-1 rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-300">Tidak Wajib</span>
                    </h2>
                    <p class="mt-1 text-xs text-slate-400">Format JPG/PNG/PDF, maks. 5MB per file, maks. 5 file.</p>

                    <label
                        x-show="attachments.length < 5"
                        class="mt-4 flex cursor-pointer items-center justify-center rounded-lg border border-dashed border-white/20 bg-white/5 p-4 text-sm text-slate-300 transition hover:border-sky-400/50 hover:bg-white/10"
                    >
                        <span>Klik untuk pilih file &mdash; <span class="text-sky-300" x-text="5 - attachments.length"></span> slot tersisa</span>
                        <input x-ref="fileInput" type="file" multiple accept=".jpg,.jpeg,.png,.pdf" class="sr-only" @change="onFilesSelected($event)">
                    </label>

                    <ul x-show="attachments.length > 0" x-cloak class="mt-4 space-y-2">
                        <template x-for="(file, index) in attachments" :key="file.name + file.size">
                            <li
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 -translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100"
                                x-transition:leave-end="opacity-0"
                                class="flex items-center justify-between gap-3 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm"
                            >
                                <div class="flex min-w-0 items-center gap-2">
                                    <svg class="h-5 w-5 shrink-0 text-sky-300" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h5.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
                                    <span class="truncate text-slate-200" x-text="file.name"></span>
                                    <span class="shrink-0 text-xs text-slate-400" x-text="formatFileSize(file.size)"></span>
                                </div>
                                <button type="button" @click="removeAttachment(index)" class="shrink-0 rounded-full p-1 text-slate-400 hover:bg-rose-500/20 hover:text-rose-300" title="Batalkan file ini">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>

                {{-- Step 5: reCAPTCHA v2 — widget Google me-render sendiri lewat script di
                     layout (components/layouts/public.blade.php) dan otomatis menyisipkan
                     input tersembunyi "g-recaptcha-response" saat form ini di-submit. --}}
                <div x-show="step === 5" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-3" x-transition:enter-end="opacity-100 translate-x-0">
                    <h2 class="text-lg font-semibold">5. Verifikasi</h2>
                    <p class="mt-1 text-xs text-slate-400">Centang kotak verifikasi di bawah ini.</p>
                    <div class="g-recaptcha mt-4" data-sitekey="{{ config('services.recaptcha.site_key') }}" data-size="compact"></div>
                </div>

                {{-- Navigation --}}
                <div class="mt-8 flex items-center justify-between">
                    <button type="button" x-show="step > 1" x-cloak @click="step--" class="rounded-lg border border-white/20 px-4 py-2 text-sm text-slate-200 transition hover:scale-[1.03] hover:bg-white/10 active:scale-95">
                        Kembali
                    </button>
                    <span x-show="step === 1"></span>

                    <button type="button" x-show="step < 5" x-cloak @click="if (canNext(step)) step++" class="ml-auto rounded-lg bg-sky-500 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-sky-500/30 transition hover:scale-[1.03] hover:bg-sky-400 active:scale-95">
                        Lanjut
                    </button>
                    <button type="submit" x-show="step === 5" x-cloak class="ml-auto rounded-lg bg-emerald-500 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-emerald-500/30 transition hover:scale-[1.03] hover:bg-emerald-400 active:scale-95">
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
                },
                attachments: [],
                canNext(currentStep) {
                    if (currentStep === 1) return this.form.type && this.form.category;
                    if (currentStep === 2) return this.form.phone;
                    if (currentStep === 3) return this.form.what;
                    if (currentStep === 5) return typeof grecaptcha === 'undefined' || grecaptcha.getResponse() !== '';
                    return true;
                },
                /**
                 * Each pick via the OS file dialog REPLACES the input's FileList entirely
                 * (browsers don't append across separate picker openings) — appended here
                 * onto whatever the citizen already picked, so a second pick adds to the
                 * list instead of discarding the first one. Capped at 5 to match the
                 * server-side limit (StoreReportRequest).
                 */
                onFilesSelected(event) {
                    this.attachments = [...this.attachments, ...Array.from(event.target.files)].slice(0, 5);
                    this.syncFileInput();
                },
                removeAttachment(index) {
                    this.attachments.splice(index, 1);
                    this.syncFileInput();
                },
                /**
                 * The actual <input type="file"> is what gets submitted — it has no native
                 * way to remove a single file from its FileList, so rebuild it from our own
                 * `attachments` array via DataTransfer every time that array changes.
                 */
                syncFileInput() {
                    const dataTransfer = new DataTransfer();
                    this.attachments.forEach((file) => dataTransfer.items.add(file));
                    this.$refs.fileInput.files = dataTransfer.files;
                },
                formatFileSize(bytes) {
                    if (bytes < 1024) return `${bytes} B`;
                    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
                    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
                },
            };
        }
    </script>
</x-layouts.public>
