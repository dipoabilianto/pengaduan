<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Detail Laporan — {{ $report->ticket_no }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Progres alur laporan — supaya Admin langsung lihat posisinya, bukan cuma
                 menebak dari opsi dropdown yang tersedia (poin audit: alur status membingungkan). --}}
            <x-admin-status-stepper :report="$report" />

            {{-- Data Pelapor — hanya Superuser. Ringkas jadi satu baris + modal, bukan card penuh. --}}
            <div class="rounded-lg bg-white p-4 shadow" x-data="{ open: {{ session('reporterIdentity') || $errors->has('reason') ? 'true' : 'false' }} }">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700">Data Pelapor</h3>
                        <p class="text-xs text-gray-400">
                            @if (session('reporterIdentity'))
                                Sudah dibuka sesi ini — tercatat di audit log.
                            @elseif ($canRevealReporter)
                                Data pelapor dilindungi UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi. Wajib isi alasan untuk membuka.
                            @else
                                Data pelapor dilindungi UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi. Hanya Superuser yang dapat membukanya.
                            @endif
                        </p>
                    </div>
                    @if (session('reporterIdentity'))
                        <button type="button" @click="open = true" class="shrink-0 rounded-md border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">Lihat</button>
                    @elseif ($canRevealReporter)
                        <button type="button" @click="open = true" class="shrink-0 rounded-md border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">Buka Data Pelapor</button>
                    @endif
                </div>

                @if (session('reporterIdentity') || $canRevealReporter)
                    <div x-show="open" x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm"
                         @keydown.escape.window="open = false" @click.self="open = false">
                        <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl" @click.stop>
                            <div class="flex items-center justify-between">
                                <h4 class="text-base font-semibold">Data Pelapor</h4>
                                <button type="button" @click="open = false" aria-label="Tutup" class="text-gray-400 hover:text-gray-600">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            @if (session('reporterIdentity'))
                                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm">
                                    <p><span class="font-medium">Nama:</span> {{ session('reporterIdentity.name') ?? '— (anonim)' }}</p>
                                    <p><span class="font-medium">No. HP (PK):</span> {{ session('reporterIdentity.phone') }}</p>
                                    <p class="mt-2 text-xs text-amber-700">Akses ini tercatat di audit log.</p>
                                </div>
                            @elseif ($canRevealReporter)
                                <form method="POST" action="{{ route('admin.reports.reveal-reporter', $report) }}" class="mt-4 space-y-2">
                                    @csrf
                                    <label class="block text-sm text-gray-600">Alasan membuka data pelapor (wajib, tercatat di audit log)</label>
                                    <textarea name="reason" rows="2" required minlength="10" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                                    @error('reason')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                                    <button type="submit" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-500">Buka Data Pelapor</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            {{-- Ringkasan Laporan (data tersamar — sesuai Bab 5.2) --}}
            <div class="rounded-lg bg-white p-6 shadow">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-lg font-semibold">Ringkasan Laporan</h3>
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium">{{ $report->statusLabel() }}</span>
                </div>

                <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase text-gray-400">Jenis</dt>
                        <dd class="mt-1 capitalize">{{ $report->type }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase text-gray-400">Kategori</dt>
                        <dd class="mt-1">{{ $report->category }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase text-gray-400">Kanal</dt>
                        <dd class="mt-1 capitalize">{{ $report->channel }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase text-gray-400">Tanggal Masuk</dt>
                        <dd class="mt-1">{{ $report->created_at->format('d M Y H:i') }}</dd>
                    </div>
                    @if ($report->reported_party)
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-400">Pihak yang Dilaporkan</dt>
                            <dd class="mt-1 font-medium text-rose-700">{{ $report->reported_party }}</dd>
                        </div>
                    @endif
                </dl>

                <div class="mt-6 space-y-4 text-sm">
                    <div>
                        <p class="text-xs font-medium uppercase text-gray-400">What</p>
                        <p class="mt-1 whitespace-pre-line rounded-lg border border-gray-100 bg-gray-50 p-3 text-base font-medium leading-relaxed text-gray-900">{{ $report->what }}</p>
                    </div>
                    @if ($report->who)
                        <div>
                            <p class="text-xs font-medium uppercase text-gray-400">Who</p>
                            <p class="mt-1 whitespace-pre-line text-base font-medium text-gray-900">{{ $report->who }}</p>
                        </div>
                    @endif
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @if ($report->where)
                            <div>
                                <p class="text-xs font-medium uppercase text-gray-400">Where</p>
                                <p class="mt-1 text-base font-medium text-gray-900">{{ $report->where }}</p>
                            </div>
                        @endif
                        @if ($report->when)
                            <div>
                                <p class="text-xs font-medium uppercase text-gray-400">When</p>
                                <p class="mt-1 text-base font-medium text-gray-900">{{ $report->when->format('d M Y H:i') }}</p>
                            </div>
                        @endif
                    </div>
                    @if ($report->how)
                        <div>
                            <p class="text-xs font-medium uppercase text-gray-400">How</p>
                            <p class="mt-1 whitespace-pre-line text-base font-medium text-gray-900">{{ $report->how }}</p>
                        </div>
                    @endif
                    @if ($report->why)
                        <div>
                            <p class="text-xs font-medium uppercase text-gray-400">Why</p>
                            <p class="mt-1 whitespace-pre-line text-base font-medium text-gray-900">{{ $report->why }}</p>
                        </div>
                    @endif
                </div>

                @if ($report->attachments->isNotEmpty())
                    @php
                        $attachmentItems = $report->attachments->values()->map(fn ($attachment) => [
                            'url' => route('admin.reports.attachments.show', [$report, $attachment]),
                            'type' => $attachment->file_type,
                            'isImage' => str_starts_with($attachment->file_type, 'image/'),
                            'uploadedAt' => $attachment->uploaded_at?->format('d/m/Y H:i'),
                        ]);
                    @endphp
                    <div class="mt-6" x-data="{ open: false, index: 0, items: @js($attachmentItems) }">
                        <p class="text-xs font-medium uppercase text-gray-400">Lampiran Bukti</p>
                        <div class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                            @foreach ($report->attachments as $i => $attachment)
                                @php $attachmentUrl = route('admin.reports.attachments.show', [$report, $attachment]); @endphp
                                <button type="button" @click="open = true; index = {{ $i }}"
                                   class="group block w-full overflow-hidden rounded-lg border border-gray-200 bg-gray-50 text-left shadow-sm transition hover:shadow-md">
                                    <div class="flex h-32 items-center justify-center bg-gray-100">
                                        @if (str_starts_with($attachment->file_type, 'image/'))
                                            <img src="{{ $attachmentUrl }}" alt="Lampiran bukti" class="h-full w-full object-cover">
                                        @else
                                            <svg class="h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                            </svg>
                                        @endif
                                    </div>
                                    <div class="p-2 text-xs">
                                        <p class="truncate text-gray-600">{{ $attachment->file_type }}</p>
                                        <p class="text-gray-400">{{ $attachment->uploaded_at->format('d/m/Y H:i') }}</p>
                                        <span class="mt-1 inline-block text-sky-600 group-hover:underline">Lihat / Unduh</span>
                                    </div>
                                </button>
                            @endforeach
                        </div>

                        {{-- Lightbox --}}
                        <div
                            x-show="open"
                            x-cloak
                            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm"
                            @keydown.escape.window="open = false"
                            @keydown.arrow-left.window="index = (index - 1 + items.length) % items.length"
                            @keydown.arrow-right.window="index = (index + 1) % items.length"
                            @click.self="open = false"
                        >
                            <button type="button" @click="open = false" aria-label="Tutup"
                                class="absolute right-4 top-4 text-white/80 hover:text-white">
                                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>

                            <button type="button" x-show="items.length > 1" aria-label="Sebelumnya"
                                @click="index = (index - 1 + items.length) % items.length"
                                class="absolute left-2 text-white/80 hover:text-white sm:left-6">
                                <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                                </svg>
                            </button>
                            <button type="button" x-show="items.length > 1" aria-label="Berikutnya"
                                @click="index = (index + 1) % items.length"
                                class="absolute right-2 text-white/80 hover:text-white sm:right-6">
                                <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                            </button>

                            <div class="w-full max-w-4xl" @click.stop>
                                <template x-if="items[index]?.isImage">
                                    <img :src="items[index].url" alt="Lampiran bukti" class="mx-auto max-h-[75vh] w-auto rounded-lg object-contain">
                                </template>
                                <template x-if="items[index] && ! items[index].isImage">
                                    <div class="mx-auto flex max-w-sm flex-col items-center gap-3 rounded-lg bg-white p-10 text-center">
                                        <svg class="h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                        </svg>
                                        <p class="text-sm text-gray-600" x-text="items[index]?.type"></p>
                                        <p class="text-xs text-gray-400">Pratinjau tidak tersedia untuk tipe berkas ini.</p>
                                    </div>
                                </template>

                                <div class="mt-4 flex items-center justify-between text-sm text-white/90">
                                    <span x-text="items.length > 1 ? (index + 1) + ' / ' + items.length + ' · ' + (items[index]?.uploadedAt ?? '') : (items[index]?.uploadedAt ?? '')"></span>
                                    <a :href="items[index]?.url" download
                                       class="inline-flex items-center gap-1.5 rounded-md bg-sky-600 px-3 py-1.5 font-medium text-white hover:bg-sky-500">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                        </svg>
                                        Unduh
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Rekomendasi Urgensi AI & Ubah Status (satu kegiatan, satu card — Bab 5.3 human-in-the-loop) --}}
            @php
                $aiPending = $report->aiAssessment && ! $report->aiAssessment->approved_flag;
                $canUpdateStatus = auth()->user()->can('updateStatus', $report);
                $isLocked = $report->isLocked();
                $isSuperuser = auth()->user()->hasRole('superuser');
                // Draf balasan sopan untuk pelapor — dipakai setiap kali urgensi berujung "Tidak Valid",
                // baik dari rekomendasi AI maupun dipilih manual oleh Admin. BUKAN alasan mentah AI
                // (yang bisa terdengar kasar/internal kalau langsung tampil ke publik).
                $rejectReplyTemplate = "Terima kasih atas laporan yang Anda sampaikan.\n\nSetelah kami tinjau, laporan ini belum dapat kami tindak lanjuti karena informasi yang diberikan belum lengkap atau belum sesuai dengan kategori pengaduan yang berlaku. Jika Anda memiliki laporan lain dengan kronologi yang lebih jelas, silakan ajukan kembali melalui sistem ini.";
            @endphp
            @if ($report->aiAssessment || $canUpdateStatus)
                <div
                    class="rounded-lg bg-white p-6 shadow"
                    x-data="{
                        loading: false,
                        error: null,
                        attempts: 0,
                        trigger() {
                            this.loading = true;
                            this.error = null;
                            this.attempts = 0;
                            axios.post('{{ route('admin.reports.rescore-ai', $report) }}')
                                .then(() => this.poll())
                                .catch((err) => {
                                    this.loading = false;
                                    this.error = err.response?.data?.errors?.ai?.[0] ?? 'Gagal memulai penilaian AI. Coba lagi.';
                                });
                        },
                        poll() {
                            this.attempts++;
                            if (this.attempts > 40) {
                                this.loading = false;
                                this.error = 'Penilaian AI butuh waktu lebih lama dari biasanya. Muat ulang halaman untuk memeriksa hasilnya.';
                                return;
                            }
                            axios.get('{{ route('admin.reports.ai-status', $report) }}')
                                .then((res) => {
                                    if (res.data.ready) {
                                        window.location.reload();
                                    } else {
                                        setTimeout(() => this.poll(), 2500);
                                    }
                                })
                                .catch(() => setTimeout(() => this.poll(), 2500));
                        },
                    }"
                >
                    <h3 class="text-lg font-semibold">Rekomendasi Urgensi AI &amp; Ubah Status</h3>

                    <div x-show="loading" x-cloak class="mt-3 flex items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-700">
                        <svg class="h-4 w-4 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span>AI sedang menilai laporan ini, mohon tunggu&hellip;</span>
                    </div>
                    <p x-show="error" x-cloak x-text="error" class="mt-3 text-sm text-rose-600"></p>

                    <div x-show="!loading">
                    @if ($report->aiAssessment)
                        <div class="mt-3 rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm">
                            <p>
                                <span class="font-medium">Flag Usulan AI:</span>
                                {{ \App\Models\Report::URGENCY_LABELS[$report->aiAssessment->ai_suggested_flag] ?? $report->aiAssessment->ai_suggested_flag }}
                                <span class="text-gray-500">(skor {{ $report->aiAssessment->ai_score }}/100)</span>
                            </p>
                            @if ($report->aiAssessment->ai_reasoning)
                                <p class="mt-1 text-gray-600">
                                    <span class="font-medium">Alasan AI:</span> {{ $report->aiAssessment->ai_reasoning }}
                                </p>
                            @endif
                            @if ($report->aiAssessment->ai_suggested_flag === 'tidak_valid')
                                <p class="mt-2 rounded-md bg-amber-100 px-3 py-2 text-amber-800">
                                    ⚠️ AI merekomendasikan flag <span class="font-medium">Tidak Valid</span> (kemungkinan iseng/spam) — kalau disetujui, laporan langsung berstatus Ditolak. Ini cuma rekomendasi, silakan tinjau dulu.
                                </p>
                            @endif
                            @if ($report->aiAssessment->approved_flag)
                                <p class="mt-1 text-emerald-700">
                                    Sudah diverifikasi Admin sebagai <span class="font-medium">{{ \App\Models\Report::URGENCY_LABELS[$report->aiAssessment->approved_flag] ?? $report->aiAssessment->approved_flag }}</span>
                                    oleh {{ $report->aiAssessment->reviewer?->name }} &middot; {{ $report->aiAssessment->reviewed_at->format('d/m/Y H:i') }}
                                </p>
                            @endif
                        </div>
                    @else
                        <p class="mt-2 text-sm text-gray-500">
                            AI belum memberi rekomendasi (belum dikonfigurasi atau gagal diproses). Silakan verifikasi urgensi secara manual di bawah.
                        </p>
                    @endif

                    @if (! $report->aiAssessment && $canUpdateStatus)
                        <div class="mt-3">
                            @if ($isAiConfigured)
                                <button type="button" @click="trigger()" class="rounded-md border border-sky-600 px-3 py-1.5 text-xs font-medium text-sky-600 hover:bg-sky-50">
                                    Nilai Ulang dengan AI
                                </button>
                                <span class="ml-2 text-xs text-gray-400">AI belum sempat menilai laporan ini — minta rekomendasi sekarang.</span>
                            @else
                                <p class="text-xs text-gray-400">AI belum dikonfigurasi, jadi laporan ini dinilai manual sepenuhnya oleh Admin di bawah.</p>
                            @endif
                        </div>
                    @endif

                    @if ($aiPending && $canApproveAi)
                        {{-- Ada rekomendasi AI yang menunggu persetujuan — satu-satunya aksi yang relevan di sini
                             adalah menyetujui/menyesuaikannya, jadi form generik status tidak ditampilkan sekaligus. --}}
                        <form method="POST" action="{{ route('admin.reports.approve-ai', $report) }}" class="mt-3 space-y-3"
                              x-data="{
                                  submitting: false,
                                  generating: false,
                                  generateError: null,
                                  finalFlag: '{{ $report->aiAssessment->ai_suggested_flag }}',
                                  reply: @js($report->aiAssessment->ai_suggested_flag === 'tidak_valid' ? $rejectReplyTemplate : ''),
                                  rejectTemplate: @js($rejectReplyTemplate),
                                  init() {
                                      // Auto-fill the polite draft the moment urgency becomes Tidak Valid —
                                      // whether that's AI's own suggestion or Admin picking it manually — but
                                      // never overwrite something Admin already typed. Just as important:
                                      // clear it back out the moment Admin switches AWAY from Tidak Valid,
                                      // as long as it's still the untouched draft (never overwrite a real
                                      // edit either way) — otherwise a stale rejection message can slip
                                      // through and get saved on a report that was actually approved, not
                                      // rejected (found on a real report: AI suggested Tidak Valid, Admin
                                      // overrode it to Rendah and approved, but the leftover penolakan
                                      // draft still got submitted as the public reply).
                                      this.$watch('finalFlag', (value) => {
                                          if (value === 'tidak_valid' && ! this.reply.trim()) {
                                              this.reply = this.rejectTemplate;
                                          } else if (value !== 'tidak_valid' && this.reply.trim() === this.rejectTemplate.trim()) {
                                              this.reply = '';
                                          }
                                      });
                                  },
                                  generateReply() {
                                      this.generating = true;
                                      this.generateError = null;
                                      axios.post('{{ route('admin.reports.reply.generate', $report) }}')
                                          .then((res) => { this.reply = res.data.reply; })
                                          .catch((err) => { this.generateError = err.response?.data?.errors?.reply?.[0] ?? 'Gagal membuat balasan dengan AI.'; })
                                          .finally(() => { this.generating = false; });
                                  },
                              }"
                              @submit.prevent="submitting = true; $nextTick(() => $el.submit())">
                            @csrf
                            <div>
                                <label for="final_flag" class="block text-xs font-medium uppercase text-gray-400">Urgensi</label>
                                <select id="final_flag" name="final_flag" x-model="finalFlag" required class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                    @foreach (\App\Models\Report::URGENCY_LABELS as $value => $label)
                                        <option value="{{ $value }}" @selected($report->aiAssessment->ai_suggested_flag === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('final_flag')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>

                            {{-- Balasan untuk Pelapor — cuma muncul saat urgensi "Tidak Valid" dipilih,
                                 karena itulah momen laporan langsung berstatus Ditolak. --}}
                            <div x-show="finalFlag === 'tidak_valid'" x-cloak>
                                <div class="flex items-center justify-between">
                                    <label for="approve_reply" class="block text-xs font-medium uppercase text-gray-400">Balasan untuk Pelapor (opsional)</label>
                                    @if ($isAiConfigured)
                                        <button type="button" @click="generateReply()" :disabled="generating"
                                                class="shrink-0 text-xs font-medium text-sky-600 hover:text-sky-500 disabled:text-gray-400">
                                            <span x-text="generating ? 'Membuat...' : '✨ Buat dengan AI'">✨ Buat dengan AI</span>
                                        </button>
                                    @endif
                                </div>
                                <textarea id="approve_reply" name="reply" rows="3" maxlength="2000" x-model="reply"
                                          x-effect="reply, finalFlag; $nextTick(() => { const cs = getComputedStyle($el); $el.style.height = 'auto'; $el.style.height = ($el.scrollHeight + parseFloat(cs.borderTopWidth) + parseFloat(cs.borderBottomWidth)) + 'px'; })"
                                          placeholder="Tulis balasan untuk pelapor..." class="mt-1 w-full resize-none overflow-hidden rounded-md border-gray-300 text-sm">{{ old('reply', $report->aiAssessment->ai_suggested_flag === 'tidak_valid' ? $rejectReplyTemplate : '') }}</textarea>
                                <p class="mt-1 text-xs text-amber-600">Draf balasan otomatis — silakan sesuaikan sebelum menyimpan, atau buat draf baru dengan AI.</p>
                                <p x-show="generateError" x-cloak x-text="generateError" class="mt-1 text-xs text-rose-600"></p>
                                @error('reply')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <button type="submit" :disabled="submitting" :class="{ 'opacity-60 cursor-not-allowed': submitting }"
                                        class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">
                                    <span x-text="submitting ? 'Menyimpan...' : 'Setujui / Sesuaikan & Verifikasi'">Setujui / Sesuaikan &amp; Verifikasi</span>
                                </button>
                                <span class="ml-2 text-xs text-gray-400" x-text="finalFlag === 'tidak_valid' ? 'Ini otomatis mengubah status ke &quot;Ditolak&quot;.' : 'Ini otomatis mengubah status ke &quot;Terverifikasi Admin&quot; dengan flag urgensi di atas.'"></span>
                            </div>
                        </form>
                    @elseif ($canUpdateStatus)
                        @php
                            // Cuma dipakai untuk pra-pilih opsi di dropdown Superuser saat membuka
                            // kembali laporan terkunci (lihat bagian "2. Status" di bawah) — di luar
                            // itu tidak relevan lagi karena status sekarang berubah lewat tombol aksi.
                            $defaultNextStatus = $report->status === 'baru_masuk' ? 'terverifikasi_admin' : $report->status;
                            // Alur status linear (ALLOWED_TRANSITIONS) berarti dari status manapun
                            // cuma ada TEPAT SATU langkah maju yang valid — jadi bisa langsung
                            // dipetakan ke satu tombol aksi berlabel jelas, bukan dropdown.
                            $forwardStatus = \App\Models\Report::ALLOWED_TRANSITIONS[$report->status][0] ?? null;
                            $forwardActionLabel = [
                                'baru_masuk' => 'Verifikasi & Lanjutkan',
                                'terverifikasi_admin' => 'Teruskan ke Pejabat',
                                'dalam_penanganan' => 'Tandai Selesai',
                            ][$report->status] ?? null;
                            // "Tandai Selesai" wajib disertai balasan — supaya laporan tidak pernah
                            // terlihat "sudah selesai" padahal pelapor belum benar-benar dapat jawaban.
                            $forwardRequiresReply = $forwardStatus === 'selesai';
                            // Urgensi sudah/akan jadi "Tidak Valid" — baik yang sudah ditetapkan
                            // (urgency_flag) maupun rekomendasi AI yang MASIH menunggu keputusan.
                            // Begitu assessment sudah di-approve, ai_suggested_flag jadi sekadar
                            // riwayat — kalau Admin meng-override rekomendasi AI (mis. AI usul
                            // "Tidak Valid" tapi Admin memutuskan "Rendah"), draf balasan penolakan
                            // tidak boleh lagi muncul karena keputusan akhirnya sudah bukan itu.
                            $isTidakValid = $report->urgency_flag === 'tidak_valid'
                                || ($report->aiAssessment && ! $report->aiAssessment->approved_flag && $report->aiAssessment->ai_suggested_flag === 'tidak_valid');
                            // Draf sopan untuk pelapor — BUKAN alasan mentah AI (bisa terdengar kasar),
                            // cuma dipakai saat urgensinya Tidak Valid dan belum ada balasan tersimpan.
                            $rejectReplyDraft = ($isTidakValid && ! $report->public_reply) ? $rejectReplyTemplate : null;
                            // Kalau laporan sudah Ditolak & sudah punya balasan, tampilkan itu supaya bisa diedit —
                            // bukan mulai kosong lagi.
                            $replyDefault = $report->public_reply ?? $rejectReplyDraft ?? '';
                        @endphp
                        <form method="POST" action="{{ route('admin.reports.status', $report) }}" class="mt-3 space-y-3"
                              x-data="{
                                  submitting: false,
                                  generating: false,
                                  generateError: null,
                                  // Tombol aksi menyetel nilai ini secara eksplisit saat diklik
                                  // (lihat @click di tiap tombol) — tidak ada lagi dropdown status
                                  // bebas untuk status non-terkunci.
                                  status: '{{ $report->status }}',
                                  urgencyFlag: '{{ $report->urgency_flag }}',
                                  reply: @js($replyDefault),
                                  originalReply: @js($report->public_reply ?? ''),
                                  rejectTemplate: @js($rejectReplyTemplate),
                                  // Urgensi 'Tidak Valid' selalu berarti status 'Ditolak' — baik yang
                                  // sudah tetap dari sebelumnya maupun baru dipilih di dropdown urgensi
                                  // di atas. effectiveStatus mencerminkan apa yang BENAR-BENAR akan
                                  // dikirim, karena dropdown status ikut disembunyikan/diganti saat itu.
                                  get effectiveStatus() {
                                      return this.urgencyFlag === 'tidak_valid' ? 'ditolak' : this.status;
                                  },
                                  init() {
                                      // Auto-fill the polite draft the moment urgency becomes Tidak Valid —
                                      // whether picked manually here or already set that way — but never
                                      // overwrite something Admin already typed. Symmetrically, revert back
                                      // to whatever reply already existed (or blank) the moment urgency is
                                      // switched away again, as long as the draft is still untouched — a
                                      // leftover rejection message must never slip through and get saved
                                      // on a report that ends up NOT rejected.
                                      this.$watch('urgencyFlag', (value) => {
                                          if (value === 'tidak_valid' && ! this.reply.trim()) {
                                              this.reply = this.rejectTemplate;
                                          } else if (value !== 'tidak_valid' && this.reply.trim() === this.rejectTemplate.trim()) {
                                              this.reply = this.originalReply;
                                          }
                                      });
                                  },
                                  generateReply() {
                                      this.generating = true;
                                      this.generateError = null;
                                      axios.post('{{ route('admin.reports.reply.generate', $report) }}')
                                          .then((res) => { this.reply = res.data.reply; })
                                          .catch((err) => { this.generateError = err.response?.data?.errors?.reply?.[0] ?? 'Gagal membuat balasan dengan AI.'; })
                                          .finally(() => { this.generating = false; });
                                  },
                              }"
                              @submit.prevent="submitting = true; $nextTick(() => $el.submit())">
                            @csrf
                            @method('PATCH')

                            {{-- 1. Urgensi Manual --}}
                            @if ($report->urgency_flag)
                                {{-- Urgensi sudah ditentukan (AI/manual) — tidak diedit lagi lewat form lanjutan ini. --}}
                                <input type="hidden" name="urgency_flag" value="{{ $report->urgency_flag }}">
                                <div>
                                    <label class="block text-xs font-medium uppercase text-gray-400">Urgensi</label>
                                    <div class="mt-1 flex items-center rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">
                                        <span class="font-medium">{{ \App\Models\Report::URGENCY_LABELS[$report->urgency_flag] ?? $report->urgency_flag }}</span>
                                    </div>
                                </div>
                            @else
                                <div>
                                    <label for="urgency_flag" class="block text-xs font-medium uppercase text-gray-400">Urgensi (Manual)</label>
                                    <select id="urgency_flag" name="urgency_flag" x-model="urgencyFlag" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                        <option value="">— Belum dinilai —</option>
                                        @foreach (\App\Models\Report::URGENCY_LABELS as $value => $label)
                                            <option value="{{ $value }}" @selected($report->urgency_flag === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            {{-- 2. Status — murni informasi, bukan kontrol interaktif. Perubahan status
                                 terjadi lewat tombol aksi di bagian "3. Simpan" di bawah, bukan lewat
                                 memilih di sini lalu klik simpan generik (poin: kurangi langkah memilih
                                 status). Superuser membuka kembali laporan terkunci tetap pakai dropdown
                                 penuh — satu-satunya pengecualian, karena butuh keluwesan koreksi. --}}
                            <div>
                                <label class="block text-xs font-medium uppercase text-gray-400">Status</label>
                                @if ($isLocked && ! $isSuperuser)
                                    {{-- Selesai/Ditolak adalah status akhir — Admin/Pejabat tidak bisa mengubahnya lagi. --}}
                                    <input type="hidden" name="status" value="{{ $report->status }}">
                                    <div class="mt-1 flex items-center justify-between rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500">
                                        <span>{{ $report->statusLabel() }}</span>
                                        <span class="text-xs text-gray-400">Terkunci — hanya Superuser yang dapat mengubah</span>
                                    </div>
                                @elseif ($isLocked && $isSuperuser)
                                    <select id="status" name="status" x-model="status" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                        @foreach ($report->validNextStatuses(true) as $value)
                                            <option value="{{ $value }}" @selected($defaultNextStatus === $value)>{{ \App\Models\Report::STATUS_LABELS[$value] }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-gray-400">Laporan ini sudah "{{ $report->statusLabel() }}" — sebagai Superuser Anda dapat mengoreksi/membuka kembali ke status manapun.</p>
                                @else
                                    {{-- Urgensi "Tidak Valid" (dari AI atau dipilih manual di atas) memaksa
                                         status ke "Ditolak" — laporan tidak valid tidak boleh diteruskan/
                                         ditangani lebih lanjut lewat status manapun selain Ditolak. --}}
                                    <template x-if="urgencyFlag === 'tidak_valid'">
                                        <div>
                                            <input type="hidden" name="status" value="ditolak">
                                            <div class="mt-1 flex items-center justify-between rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                                                <span class="font-medium">Ditolak</span>
                                                <span class="text-xs text-amber-600">Otomatis — urgensi Tidak Valid</span>
                                            </div>
                                            <p class="mt-1 text-xs text-gray-400">Laporan dengan urgensi "Tidak Valid" otomatis berstatus "Ditolak". Ubah urgensi di atas kalau ini bukan yang dimaksud.</p>
                                        </div>
                                    </template>
                                    <template x-if="urgencyFlag !== 'tidak_valid'">
                                        <div>
                                            <input type="hidden" name="status" x-model="status">
                                            <div class="mt-1 flex items-center rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">
                                                <span class="font-medium">{{ $report->statusLabel() }}</span>
                                            </div>
                                        </div>
                                    </template>
                                @endif
                                @error('status')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>

                            @if ($report->status === 'terverifikasi_admin')
                                {{-- Satu-satunya aksi maju dari status ini memang "Teruskan ke Pejabat"
                                     (langsung memindahkan status ke "Dalam Penanganan" sekaligus mencatat
                                     pejabatnya — tidak ada lagi klik terpisah "Mulai Tangani"), jadi field
                                     ini cukup selalu tampil saat urgensi bukan Tidak Valid. Tetap `required`
                                     hanya secara reaktif ke tombol yang benar-benar diklik
                                     (status === 'dalam_penanganan') — supaya tombol "Tolak Laporan" tidak
                                     ikut diblokir validasi HTML5 gara-gara field ini kosong. --}}
                                <div x-show="urgencyFlag !== 'tidak_valid'" x-cloak>
                                    <label for="pejabat_id" class="block text-xs font-medium uppercase text-gray-400">Teruskan ke Pejabat</label>
                                    <select id="pejabat_id" name="pejabat_id" :required="status === 'dalam_penanganan'" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                        <option value="">— Pilih Pejabat —</option>
                                        @foreach ($pejabatList as $pejabat)
                                            <option value="{{ $pejabat->id }}">{{ $pejabat->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('pejabat_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                </div>
                            @endif

                            {{-- Balasan untuk Pelapor — cuma relevan begitu ada sesuatu yang benar-benar
                                 layak disampaikan: Ditolak (alasan penolakan), Dalam Penanganan (kabar
                                 progres), atau Selesai (info penyelesaian). Disembunyikan di Baru Masuk/
                                 Terverifikasi Admin/Diteruskan ke Pejabat — itu tahap administratif
                                 internal, belum ada apa pun untuk disampaikan ke pelapor. Reaktif ke
                                 effectiveStatus (bukan status statis milik laporan) supaya field ini
                                 langsung muncul begitu Admin memilih urgensi "Tidak Valid" di laporan
                                 yang belum diverifikasi AI, tanpa perlu submit dulu. --}}
                            @if ($isLocked && ! $isSuperuser)
                                @if ($report->public_reply)
                                    <div>
                                        <label class="block text-xs font-medium uppercase text-gray-400">Balasan untuk Pelapor</label>
                                        <div class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500">{{ $report->public_reply }}</div>
                                        <p class="mt-1 text-xs text-gray-400">Laporan sudah "{{ $report->statusLabel() }}" — balasan terkunci, hanya Superuser yang dapat mengubahnya.</p>
                                    </div>
                                @endif
                            @else
                                <div x-show="['ditolak', 'dalam_penanganan', 'selesai'].includes(effectiveStatus)" x-cloak>
                                    <div class="flex items-center justify-between">
                                        <label for="status_reply" class="block text-xs font-medium uppercase text-gray-400">Balasan untuk Pelapor (opsional)</label>
                                        @if ($isAiConfigured)
                                            <button type="button" @click="generateReply()" x-show="effectiveStatus === 'ditolak'" x-cloak :disabled="generating"
                                                    class="shrink-0 text-xs font-medium text-sky-600 hover:text-sky-500 disabled:text-gray-400">
                                                <span x-text="generating ? 'Membuat...' : '✨ Buat dengan AI'">✨ Buat dengan AI</span>
                                            </button>
                                        @endif
                                    </div>
                                    <textarea id="status_reply" name="reply" rows="3" maxlength="2000" x-model="reply"
                                              x-effect="reply, effectiveStatus; $nextTick(() => { const cs = getComputedStyle($el); $el.style.height = 'auto'; $el.style.height = ($el.scrollHeight + parseFloat(cs.borderTopWidth) + parseFloat(cs.borderBottomWidth)) + 'px'; })"
                                              placeholder="Tulis balasan untuk pelapor — mis. kabar progres, informasi penyelesaian, atau alasan penolakan..." class="mt-1 w-full resize-none overflow-hidden rounded-md border-gray-300 text-sm">{{ old('reply', $replyDefault) }}</textarea>
                                    <p class="mt-1 text-xs text-gray-400">Ditampilkan ke pelapor di halaman cek status. Kosongkan kalau belum perlu membalas.</p>
                                    @if ($report->public_reply)
                                        <p class="mt-1 text-xs text-gray-400">Balasan saat ini — ubah lalu Simpan untuk memperbarui.</p>
                                    @endif
                                    @if ($rejectReplyDraft)
                                        <p class="mt-1 text-xs text-amber-600">Draf balasan otomatis karena urgensi Tidak Valid — silakan sesuaikan sebelum menyimpan, atau buat draf baru dengan AI.</p>
                                    @endif
                                    <p x-show="generateError" x-cloak x-text="generateError" class="mt-1 text-xs text-rose-600"></p>
                                    @error('reply')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                </div>
                            @endif

                            {{-- 3. Aksi — status berubah langsung saat tombol diklik, bukan lewat
                                 pilih-dropdown-lalu-simpan-generik. Superuser yang membuka kembali
                                 laporan terkunci tetap pakai tombol simpan generik karena statusnya
                                 bebas dipilih dari dropdown di atas, bukan satu aksi tetap. --}}
                            @if ($isLocked && $isSuperuser)
                                <button type="submit" :disabled="submitting" :class="{ 'opacity-60 cursor-not-allowed': submitting }"
                                        class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">
                                    <span x-text="submitting ? 'Menyimpan...' : 'Simpan Perubahan'">Simpan Perubahan</span>
                                </button>
                            @elseif (! $isLocked)
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                    @if ($forwardActionLabel)
                                        <div>
                                            <button type="submit" @click="status = '{{ $forwardStatus }}'"
                                                    x-show="urgencyFlag !== 'tidak_valid'" x-cloak
                                                    :disabled="submitting || !urgencyFlag || ({{ $forwardRequiresReply ? 'true' : 'false' }} && !reply.trim())"
                                                    :class="{ 'opacity-60 cursor-not-allowed': submitting || !urgencyFlag || ({{ $forwardRequiresReply ? 'true' : 'false' }} && !reply.trim()) }"
                                                    class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">
                                                <span x-text="submitting ? 'Menyimpan...' : '{{ $forwardActionLabel }}'">{{ $forwardActionLabel }}</span>
                                            </button>
                                            @if ($forwardRequiresReply)
                                                {{-- Mencegah laporan terlihat "sudah selesai" padahal pelapor
                                                     belum benar-benar dapat jawaban — balasan wajib diisi dulu. --}}
                                                <p x-show="!reply.trim()" x-cloak class="mt-1 text-xs text-amber-600">
                                                    Isi balasan untuk pelapor dulu sebelum menandai selesai.
                                                </p>
                                            @endif
                                        </div>
                                    @endif

                                    <button type="submit" @click="status = 'ditolak'"
                                            onclick="return confirm('Yakin tolak laporan ini? Laporan akan langsung berstatus Ditolak.')"
                                            :disabled="submitting || !urgencyFlag" :class="{ 'opacity-60 cursor-not-allowed': submitting || !urgencyFlag }"
                                            class="rounded-md border border-rose-300 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50 sm:ml-auto">
                                        <span x-text="submitting ? 'Menyimpan...' : 'Tolak Laporan'">Tolak Laporan</span>
                                    </button>
                                </div>
                            @endif
                        </form>
                    @elseif ($aiPending)
                        <p class="mt-3 text-sm text-gray-500">Menunggu persetujuan Admin atas rekomendasi AI di atas.</p>
                    @endif
                    </div>
                </div>
            @endif

            {{-- Balasan untuk Pelapor sekarang jadi satu form dengan card di atas (lihat field
                 "Balasan untuk Pelapor" yang muncul saat status "Ditolak" dipilih). Ini cuma info
                 balasan yang sudah tersimpan + tombol hapus, karena keduanya butuh submit terpisah. --}}
            @if ($canUpdateStatus && $report->public_reply && (! $isLocked || $isSuperuser))
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs text-gray-400">
                            Balasan tersimpan oleh {{ $report->publicReplyAuthor?->name ?? '—' }} &middot; {{ $report->public_reply_at?->format('d/m/Y H:i') }}
                        </p>
                        <form method="POST" action="{{ route('admin.reports.reply.destroy', $report) }}"
                              onsubmit="return confirm('Hapus balasan ini? Pelapor tidak akan melihatnya lagi.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="shrink-0 rounded-md border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50">Hapus Balasan</button>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Penugasan pertama kali ditangani inline di form status di atas — card ini
                 cuma untuk MENGALIHKAN laporan yang sudah diteruskan ke pejabat lain, jadi
                 judul & keterangannya harus jelas beda dari aksi "Diteruskan ke Pejabat" di
                 form utama, supaya tidak terlihat seperti duplikat. --}}
            @if ($canAssign && $report->status === 'dalam_penanganan')
                @php $currentAssignee = $report->assignments->last()?->assignee; @endphp
                <div class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-lg font-semibold">Alihkan ke Pejabat Lain</h3>
                    <p class="mt-1 text-xs text-gray-400">
                        @if ($currentAssignee)
                            Laporan ini sedang ditangani oleh <span class="font-medium text-gray-600">{{ $currentAssignee->name }}</span>. Gunakan ini kalau perlu dialihkan ke pejabat lain.
                        @else
                            Alihkan laporan yang sudah diteruskan ini ke pejabat lain.
                        @endif
                    </p>
                    <form method="POST" action="{{ route('admin.reports.assign', $report) }}" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        @csrf
                        <select name="pejabat_id" required class="w-full rounded-md border-gray-300 text-sm sm:col-span-2">
                            <option value="">— Pilih Pejabat —</option>
                            @foreach ($pejabatList as $pejabat)
                                <option value="{{ $pejabat->id }}">{{ $pejabat->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Alihkan</button>
                        @error('pejabat_id')<p class="text-xs text-rose-600 sm:col-span-3">{{ $message }}</p>@enderror
                    </form>
                </div>
            @endif

            {{-- Riwayat --}}
            <div class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold">Riwayat Status</h3>
                <ul class="mt-3 space-y-3 text-sm">
                    @forelse ($report->statusLogs as $log)
                        <li class="border-l-2 border-sky-200 pl-3">
                            @if ($log->old_status === $log->new_status)
                                {{-- Status tidak benar-benar berubah — ini cuma penambahan catatan
                                     (mis. anotasi manual admin, atau rekomendasi AI). --}}
                                <p>
                                    <span class="font-medium">{{ $log->actor?->name ?? 'Sistem' }}</span> menambahkan catatan
                                    <span class="text-gray-400">(status tetap {{ \App\Models\Report::STATUS_LABELS[$log->new_status] ?? $log->new_status }})</span>
                                </p>
                            @else
                                <p><span class="font-medium">{{ $log->actor?->name ?? 'Sistem' }}</span> mengubah status ke
                                    <span class="font-medium">{{ \App\Models\Report::STATUS_LABELS[$log->new_status] ?? $log->new_status }}</span>
                                </p>
                            @endif
                            @if ($log->note)
                                <p class="text-gray-500">{{ $log->note }}</p>
                            @endif
                            <p class="text-xs text-gray-400">{{ $log->created_at->format('d/m/Y H:i') }}</p>
                        </li>
                    @empty
                        <li class="text-gray-400">Belum ada riwayat perubahan status.</li>
                    @endforelse
                </ul>
            </div>

            @if ($report->assignments->isNotEmpty())
                <div class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-lg font-semibold">Riwayat Penerusan</h3>
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ($report->assignments as $assignment)
                            <li>
                                Diteruskan ke <span class="font-medium">{{ $assignment->assignee?->name }}</span>
                                oleh {{ $assignment->assigner?->name }}
                                &middot; {{ $assignment->assigned_at->format('d/m/Y H:i') }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <a href="{{ route('admin.reports.index') }}" class="inline-block text-sm text-sky-600 hover:underline">&larr; Kembali ke daftar laporan</a>
        </div>
    </div>
</x-app-layout>
