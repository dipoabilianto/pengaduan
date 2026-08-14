<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Chat — {{ $ticket->statusLabel() }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 space-y-4">

            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="rounded-lg bg-white p-4 shadow">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm text-gray-600">
                        <p>Petugas: <span class="font-medium text-gray-800">{{ $ticket->assignedOfficer?->name ?? 'Belum diambil' }}</span></p>
                        @if ($ticket->relatedReport)
                            <p class="mt-1 text-xs text-gray-400">Terkait laporan: <a href="{{ route('admin.reports.show', $ticket->relatedReport) }}" class="text-sky-600 hover:underline">{{ $ticket->relatedReport->ticket_no }}</a></p>
                        @endif
                        <p class="mt-1 text-xs text-gray-400">
                            AI otomatis: <span @class(['font-medium', 'text-emerald-600' => $ticket->ai_enabled, 'text-gray-400' => ! $ticket->ai_enabled])>{{ $ticket->ai_enabled ? 'Aktif' : 'Nonaktif' }}</span>
                        </p>
                        @if ($ticket->ratings->isNotEmpty())
                            <div class="mt-2 space-y-1">
                                @foreach ($ticket->ratings as $rating)
                                    <p class="text-xs text-gray-500">
                                        {{ $rating->scaleEmoji() }} <span class="font-medium text-gray-700">{{ $rating->scaleLabel() }}</span>
                                        @if ($rating->comment)
                                            — "{{ $rating->comment }}"
                                        @endif
                                        <span class="text-gray-400">({{ $rating->created_at->diffForHumans() }})</span>
                                    </p>
                                @endforeach
                            </div>
                        @endif
                        @if ($canViewPhone)
                            @if (session('chatIdentity'))
                                <p class="mt-1 text-xs text-amber-700">Nomor HP: <span class="font-medium">{{ session('chatIdentity.phone') }}</span> — tercatat di audit log.</p>
                            @else
                                <form method="POST" action="{{ route('admin.chat.reveal-phone', $ticket) }}" class="mt-1">
                                    @csrf
                                    <button type="submit" class="text-xs font-medium text-amber-700 hover:underline">Buka nomor HP</button>
                                </form>
                            @endif
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if ($canReply && ! $ticket->assigned_to)
                            <form method="POST" action="{{ route('admin.chat.claim', $ticket) }}">
                                @csrf
                                <button type="submit" class="rounded-md border border-sky-300 px-3 py-1.5 text-xs font-medium text-sky-700 hover:bg-sky-50">Ambil Tiket</button>
                            </form>
                        @endif
                        @if ($canReply)
                            <form method="POST" action="{{ route('admin.chat.toggle-ai', $ticket) }}">
                                @csrf
                                <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                    {{ $ticket->ai_enabled ? 'Nonaktifkan AI' : 'Aktifkan AI' }}
                                </button>
                            </form>
                        @endif
                        @if ($canClose)
                            <form method="POST" action="{{ route('admin.chat.status', $ticket) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ $ticket->isClosed() ? 'menunggu_respon' : 'selesai' }}">
                                <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                    {{ $ticket->isClosed() ? 'Buka Kembali' : 'Tandai Selesai' }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            <div class="rounded-lg bg-white shadow">
                <div id="chat-thread" data-ticket-id="{{ $ticket->id }}" class="h-96 space-y-2 overflow-y-auto p-4">
                    @foreach ($ticket->messages as $message)
                        @php
                            // Officer and AI both speak "for the office" — grouped on the same
                            // (right) side, distinguished only by color, so at a glance the
                            // citizen's side (left) vs. the office's side (right) is what reads
                            // first, and who-on-our-side is the secondary detail.
                            $isOfficer = $message->sender_type === 'officer';
                            $isAi = $message->sender_type === 'ai';
                            $isOrgSide = $isOfficer || $isAi;
                            $senderName = match ($message->sender_type) {
                                'officer' => $message->sender?->name ?? 'Petugas ULP',
                                'ai' => 'Adelia Natata Herbi',
                                'system' => 'Sistem',
                                default => 'Pelapor',
                            };
                        @endphp
                        <div @class([
                            'max-w-[75%] rounded-lg px-3 py-2 text-sm mb-2',
                            'ml-auto bg-sky-600 text-white' => $isOfficer,
                            'ml-auto bg-emerald-600 text-white' => $isAi,
                            'mr-auto bg-white text-gray-700 border border-gray-200' => ! $isOrgSide,
                        ])>
                            <p @class(['mb-0.5 text-xs font-semibold', 'text-sky-100' => $isOfficer, 'text-emerald-100' => $isAi, 'text-sky-600' => ! $isOrgSide])>{{ $senderName }}</p>
                            <p class="whitespace-pre-line">{{ $message->body }}</p>
                            <p class="mt-1 text-right text-[10px] opacity-60">{{ $message->created_at?->format('H.i.s') }}</p>
                        </div>
                    @endforeach
                </div>

                <p id="chat-typing-indicator" class="hidden border-t border-gray-100 px-4 py-1.5 text-xs italic text-gray-400">
                    Pelapor sedang mengetik&hellip;
                </p>

                @if ($canReply)
                    <form @submit.prevent="send()" class="border-t border-gray-200 p-3"
                          x-data="{
                              draft: {{ Js::from(old('message', '')) }},
                              rawDraft: null,
                              polishing: false,
                              polishError: null,
                              sending: false,
                              sendError: null,
                              polish() {
                                  if (! this.draft.trim() || this.polishing) return;
                                  this.polishing = true;
                                  this.polishError = null;
                                  const original = this.draft;
                                  axios.post('{{ route('admin.chat.polish', $ticket) }}', { draft: original })
                                      .then((res) => {
                                          this.rawDraft = original;
                                          this.draft = res.data.polished;
                                      })
                                      .catch((err) => {
                                          this.polishError = err.response?.data?.errors?.draft?.[0] ?? 'Gagal merapikan balasan dengan AI.';
                                      })
                                      .finally(() => { this.polishing = false; });
                              },
                              // AJAX instead of a plain form POST — that used to reload the
                              // whole page on every reply, which reset #chat-thread's own
                              // scroll position back to the top each time (not just the
                              // page). The new bubble itself is appended by the existing
                              // '.message.sent' broadcast listener (admin-chat.js), which
                              // already scrolls the thread to the bottom afterward.
                              send() {
                                  if (! this.draft.trim() || this.sending) return;
                                  this.sending = true;
                                  this.sendError = null;
                                  axios.post('{{ route('admin.chat.send', $ticket) }}', { message: this.draft, raw_draft: this.rawDraft })
                                      .then(() => {
                                          this.draft = '';
                                          this.rawDraft = null;
                                      })
                                      .catch((err) => {
                                          this.sendError = err.response?.data?.errors?.message?.[0] ?? 'Gagal mengirim balasan.';
                                      })
                                      .finally(() => { this.sending = false; });
                              },
                          }">
                        @csrf
                        <div class="flex items-center gap-2">
                            <input type="text" id="chat-composer-input" x-model="draft" required maxlength="2000" placeholder="Tulis balasan..."
                                   class="flex-1 rounded-md border-gray-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                            @if ($isAiConfigured)
                                <button type="button" @click="polish()" :disabled="polishing"
                                        class="shrink-0 flex items-center gap-1.5 rounded-md border border-sky-300 px-3 py-2 text-xs font-medium text-sky-700 hover:bg-sky-50 disabled:opacity-60">
                                    <svg x-show="polishing" x-cloak class="h-3.5 w-3.5 animate-spin text-sky-700" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    <span x-text="polishing ? 'Merapikan… (bisa sampai 20 detik)' : 'Haluskan dengan AI'">Haluskan dengan AI</span>
                                </button>
                            @endif
                            <button type="submit" :disabled="sending" class="shrink-0 rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500 disabled:opacity-60">
                                <span x-text="sending ? 'Mengirim...' : 'Kirim'">Kirim</span>
                            </button>
                        </div>
                        <p x-show="polishError" x-cloak x-text="polishError" class="mt-1 text-xs text-rose-600"></p>
                        <p x-show="sendError" x-cloak x-text="sendError" class="mt-1 text-xs text-rose-600"></p>
                    </form>
                @endif
            </div>
        </div>
    </div>

    @vite('resources/js/admin-chat.js')
</x-app-layout>
