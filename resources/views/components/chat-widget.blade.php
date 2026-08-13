{{--
    Maskot ULP mengambang — dua pintu masuk: klik langsung tombol ini, atau event
    "chat-widget:open" (lihat public/status/result.blade.php) dari tombol "Chat dengan
    Petugas". Logika Echo/network ada di resources/js/chat-widget.js (x-data="chatWidget()"
    dipasok dari sana sebagai fungsi global, bukan Alpine.data() — lihat komentar di file itu).
--}}
<div x-data="chatWidget()" x-cloak class="fixed bottom-5 right-5 z-50">
    <div x-show="open" x-transition x-cloak
         class="mb-3 flex h-[28rem] w-80 flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl">
        <div class="flex items-center justify-between bg-sky-600 px-4 py-3 text-white">
            <div class="flex items-center gap-2">
                <img src="{{ asset('images/maskot-ulp.png') }}" alt="Maskot ULP" class="h-10 w-8 shrink-0 rounded-md object-cover object-top">
                <div>
                    <p class="text-sm font-semibold">Adelia Natata Herbi</p>
                    <p class="text-xs text-sky-100">Unit Layanan Pengaduan</p>
                </div>
            </div>
            <button type="button" @click="open = false" class="text-sky-100 hover:text-white">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            </button>
        </div>

        <template x-if="step === 'phone'">
            <div class="flex flex-1 flex-col justify-center gap-3 px-4 py-6">
                <p class="text-sm text-gray-600">Masukkan nomor HP Anda untuk mulai chat dengan petugas.</p>
                <input type="tel" x-model="phone" placeholder="08xxxxxxxxxx"
                       @keydown.enter="startChat()"
                       class="w-full rounded-md border-gray-300 text-sm text-gray-900 focus:border-sky-500 focus:ring-sky-500">
                <div class="flex items-center gap-2">
                    <div class="flex h-9 min-w-[110px] items-center justify-center rounded-md border border-gray-200 bg-gray-50 px-3">
                        <span class="font-mono text-lg font-bold tracking-[0.3em] text-gray-800" x-text="captchaCode || '…'"></span>
                    </div>
                    <button type="button" @click="refreshCaptcha()" title="Ganti Kode"
                            class="rounded-md border border-gray-200 px-2 py-1.5 text-xs text-gray-500 hover:bg-gray-50">
                        &#8635;
                    </button>
                </div>
                <input type="text" x-model="captcha" placeholder="Ketik ulang kode di atas" maxlength="5"
                       @keydown.enter="startChat()"
                       class="w-full rounded-md border-gray-300 text-sm uppercase tracking-widest text-gray-900 focus:border-sky-500 focus:ring-sky-500">
                <p x-show="error" x-cloak x-text="error" class="text-xs text-rose-600"></p>
                <button type="button" @click="startChat()" :disabled="starting"
                        class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500 disabled:opacity-60">
                    <span x-text="starting ? 'Menghubungkan...' : 'Mulai Chat'">Mulai Chat</span>
                </button>
            </div>
        </template>

        <div x-show="step === 'chat'" class="flex flex-1 flex-col overflow-hidden">
            <div x-show="historyHidden" x-cloak class="border-b border-gray-100 bg-gray-50 px-3 py-2 text-center">
                <button type="button" @click="revealHistory()" :disabled="revealing"
                        class="text-xs font-medium text-sky-600 hover:text-sky-700 disabled:opacity-60">
                    <span x-text="revealing ? 'Memuat...' : 'Lihat Riwayat Chat Sebelumnya'">Lihat Riwayat Chat Sebelumnya</span>
                </button>
            </div>
            <div id="chat-widget-thread" x-ref="thread" class="flex-1 space-y-2 overflow-y-auto bg-gray-50 px-3 py-3">
                <template x-for="message in messages" :key="message.id">
                    <div :class="message.sender_type === 'citizen' ? 'ml-auto bg-sky-600 text-white' : 'mr-auto bg-white text-gray-700 border border-gray-200'"
                         class="max-w-[85%] rounded-lg px-3 py-2 text-sm">
                        <p x-show="message.sender_type !== 'citizen'" x-text="message.sender_name" class="mb-0.5 text-xs font-semibold text-sky-600"></p>
                        <p x-text="message.body" class="whitespace-pre-line"></p>
                        <template x-if="ctaFor(message.cta_action)">
                            <a :href="ctaFor(message.cta_action)?.href" target="_blank" rel="noopener"
                               class="mt-2 inline-block rounded-md bg-sky-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-sky-500"
                               x-text="ctaFor(message.cta_action)?.label"></a>
                        </template>
                    </div>
                </template>
                <p x-show="officerTyping" x-cloak class="mr-auto max-w-[85%] rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs italic text-gray-400">
                    Petugas sedang mengetik&hellip;
                </p>
            </div>
            <div x-show="awaitingRating" x-cloak class="border-t border-gray-100 bg-gray-50 px-3 py-3 text-center">
                <p class="mb-2 text-xs font-medium text-gray-600">Gimana pelayanan chat kali ini?</p>
                <div class="mb-2 flex justify-center gap-3 text-2xl">
                    <button type="button" @click="selectedRatingScale = 1" :class="selectedRatingScale === 1 ? 'ring-2 ring-sky-500 rounded-full' : ''">😞</button>
                    <button type="button" @click="selectedRatingScale = 2" :class="selectedRatingScale === 2 ? 'ring-2 ring-sky-500 rounded-full' : ''">🙁</button>
                    <button type="button" @click="selectedRatingScale = 3" :class="selectedRatingScale === 3 ? 'ring-2 ring-sky-500 rounded-full' : ''">🙂</button>
                    <button type="button" @click="selectedRatingScale = 4" :class="selectedRatingScale === 4 ? 'ring-2 ring-sky-500 rounded-full' : ''">😄</button>
                </div>
                <template x-if="selectedRatingScale">
                    <div class="space-y-2">
                        <input type="text" x-model="ratingComment" placeholder="Komentar (opsional)"
                               class="w-full rounded-md border-gray-300 text-xs text-gray-900 focus:border-sky-500 focus:ring-sky-500">
                        <button type="button" @click="sendRating()" :disabled="ratingSaving"
                                class="rounded-md bg-sky-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-sky-500 disabled:opacity-60">
                            <span x-text="ratingSaving ? 'Mengirim...' : 'Kirim Penilaian'">Kirim Penilaian</span>
                        </button>
                    </div>
                </template>
                <button type="button" @click="dismissRating()" class="mt-2 block w-full text-xs text-gray-400 hover:text-gray-600">Nanti saja</button>
            </div>
            <p x-show="ratingSubmitted" x-cloak class="border-t border-gray-100 bg-gray-50 px-3 py-2 text-center text-xs text-emerald-600">
                Terima kasih atas penilaian Anda! 🙏
            </p>
            <form @submit.prevent="send()" class="flex items-center gap-2 border-t border-gray-200 p-2">
                <input type="text" x-model="draft" @input="notifyTyping()" placeholder="Tulis pesan..."
                       class="flex-1 rounded-md border-gray-300 text-sm text-gray-900 focus:border-sky-500 focus:ring-sky-500">
                <button type="submit" :disabled="sending"
                        class="shrink-0 rounded-md bg-sky-600 px-3 py-2 text-sm font-medium text-white hover:bg-sky-500 disabled:opacity-60">
                    Kirim
                </button>
            </form>
            <p x-show="error" x-cloak x-text="error" class="px-3 pb-2 text-xs text-rose-600"></p>
        </div>
    </div>

    <button type="button" @click="toggle()"
            :class="{ 'maskot-idle': !open }"
            class="relative block h-40 w-28 drop-shadow-xl transition hover:scale-105">
        <span x-show="unread > 0" x-cloak x-text="unread"
              class="absolute -top-1 -right-1 z-10 flex h-5 w-5 items-center justify-center rounded-full bg-rose-500 text-xs font-bold text-white shadow"></span>
        <img src="{{ asset('images/maskot-ulp.png') }}" alt="Chat dengan Adelia Natata Herbi"
             class="h-full w-full object-contain object-bottom">
    </button>
</div>
