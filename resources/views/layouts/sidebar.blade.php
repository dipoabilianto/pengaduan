<!-- Mobile overlay -->
<div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-30 bg-gray-900/40 lg:hidden"></div>

<aside
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    class="fixed inset-y-0 left-0 z-40 flex h-screen w-64 shrink-0 -translate-x-full flex-col border-r border-gray-200 bg-white transition-transform duration-200 ease-in-out lg:sticky lg:top-0 lg:z-auto lg:translate-x-0"
>
    <div class="flex h-16 shrink-0 items-center justify-between border-b border-gray-100 px-4">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2">
            @if ($logoUrl ?? null)
                <img src="{{ $logoUrl }}" alt="Logo" class="h-9 w-auto max-w-[9rem] shrink-0 object-contain">
            @else
                <x-application-logo class="h-8 w-8 shrink-0 fill-current text-gray-800" />
            @endif
            <span class="min-w-0 leading-tight">
                <span class="block truncate text-sm font-semibold text-gray-800">{{ config('app.name') }}</span>
                <span class="block truncate text-[10px] text-gray-400">Kanal Terpadu Aspirasi dan Kritik Kita</span>
            </span>
        </a>
        <button @click="sidebarOpen = false" class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 lg:hidden">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
        <x-sidebar-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
            </svg>
            {{ __('Dashboard') }}
        </x-sidebar-nav-link>

        @can('viewAny', \App\Models\Report::class)
            @php $onReports = request()->routeIs('admin.reports.*'); @endphp
            {{-- Nama state Alpine sengaja bukan "open" polos — kalau di halaman ini ada
                 modal lain yang juga pakai x-data "{ open: ... }" (mis. modal buka data
                 pelapor), test yang men-scan seluruh HTML untuk "open: true"/"open: false"
                 bisa salah tangkap punya sidebar ini. --}}
            <div x-data="{ reportsMenuOpen: {{ $onReports ? 'true' : 'false' }} }">
                <button
                    @click="reportsMenuOpen = ! reportsMenuOpen"
                    type="button"
                    @class([
                        'flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium',
                        'bg-sky-50 text-sky-700' => $onReports,
                        'text-gray-600 hover:bg-gray-100 hover:text-gray-900' => ! $onReports,
                    ])
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                    <span class="flex-1 text-left">{{ __('Laporan') }}</span>
                    <svg class="h-4 w-4 shrink-0 transition-transform duration-150" :class="{ 'rotate-90': reportsMenuOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </button>

                <div x-show="reportsMenuOpen" x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="mt-1 space-y-0.5 pl-8 pr-1">
                    {{-- Hanya status yang memang jadi hak role user yang login (Report::visibleStatusesFor)
                         — bukan seluruh STATUS_LABELS, supaya sidebar tidak menawarkan tautan ke status
                         yang toh akan ditolak (403) kalau diklik. --}}
                    @foreach (\App\Models\Report::STATUS_LABELS as $key => $label)
                        @continue(isset($visibleReportStatuses) && ! in_array($key, $visibleReportStatuses, true))
                        @php
                            // Di halaman daftar, cocokkan query string seperti biasa. Di halaman
                            // detail, ikuti status laporan yang sedang dilihat (lihat komentar di
                            // AppServiceProvider) supaya highlight tidak "nyangkut" di status lama
                            // begitu admin mengubah status laporan dari halaman detail.
                            $isActiveStatus = request()->routeIs('admin.reports.show')
                                ? ($currentReportStatus ?? null) === $key
                                : request('status') === $key;
                        @endphp
                        <x-sidebar-nav-link :href="route('admin.reports.index', ['status' => $key])" :active="$isActiveStatus" class="justify-between py-1.5 text-[13px]">
                            {{ $label }}
                            @isset($reportStatusCounts)
                                <span class="text-[11px] text-gray-400">{{ $reportStatusCounts[$key] }}</span>
                            @endisset
                        </x-sidebar-nav-link>
                    @endforeach
                    {{-- "Semua" sengaja ditaruh paling bawah, setelah status "Ditolak/Tidak Valid" —
                         diminta pengguna. Dicek ke route index secara spesifik, BUKAN $onReports (yang
                         juga true di halaman detail) — kalau tidak, "Semua" akan salah menyala begitu
                         admin membuka detail laporan, padahal ia datang dari filter status lain
                         (mis. "Diteruskan ke Pejabat"). Item status di atas sudah benar karena memang
                         cuma menyala kalau query string "status" cocok persis. --}}
                    <x-sidebar-nav-link :href="route('admin.reports.index')" :active="request()->routeIs('admin.reports.index') && blank(request('status'))" class="justify-between py-1.5 text-[13px]">
                        {{ __('Semua') }}
                        @isset($reportStatusCounts)
                            <span class="text-[11px] text-gray-400">{{ array_sum($reportStatusCounts) }}</span>
                        @endisset
                    </x-sidebar-nav-link>
                </div>
            </div>
        @endcan

        @can('viewAny', \App\Models\ChatTicket::class)
            <x-sidebar-nav-link :href="route('admin.chat.index')" :active="request()->routeIs('admin.chat.*')" class="justify-between">
                <span class="flex items-center gap-2">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                    </svg>
                    {{ __('Live Chat') }}
                </span>
                @isset($chatWaitingCount)
                    @if ($chatWaitingCount > 0)
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700">{{ $chatWaitingCount }}</span>
                    @endif
                @endisset
            </x-sidebar-nav-link>
        @endcan

        @can('viewAny', \App\Models\ChannelInbox::class)
            <x-sidebar-nav-link :href="route('admin.inbox.index')" :active="request()->routeIs('admin.inbox.*')">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                </svg>
                {{ __('Inbox Terpadu') }}
            </x-sidebar-nav-link>
        @endcan

        @role('superuser')
            <p class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('Pengaturan') }}</p>
            <x-sidebar-nav-link :href="route('admin.settings.ai')" :active="request()->routeIs('admin.settings.ai')">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M15.75 3v1.5M8.25 19.5V21M15.75 19.5V21M3 8.25H1.5m21 0H21m0 7.5h1.5m-1.5-7.5v7.5m0-7.5H3M3 15.75H1.5m1.5-7.5v7.5m13.5-10.5H8.25a2.25 2.25 0 00-2.25 2.25v7.5a2.25 2.25 0 002.25 2.25h7.5a2.25 2.25 0 002.25-2.25v-7.5a2.25 2.25 0 00-2.25-2.25z" />
                </svg>
                {{ __('Pengaturan AI') }}
            </x-sidebar-nav-link>
            <x-sidebar-nav-link :href="route('admin.settings.branding')" :active="request()->routeIs('admin.settings.branding')">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5z" />
                </svg>
                {{ __('Logo & Favicon') }}
            </x-sidebar-nav-link>
            <x-sidebar-nav-link :href="route('admin.settings.whatsapp')" :active="request()->routeIs('admin.settings.whatsapp')">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                </svg>
                {{ __('Pengaturan WhatsApp') }}
            </x-sidebar-nav-link>
            <x-sidebar-nav-link :href="route('admin.settings.instagram')" :active="request()->routeIs('admin.settings.instagram')">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75h9A3.75 3.75 0 0120.25 7.5v9a3.75 3.75 0 01-3.75 3.75h-9A3.75 3.75 0 013 16.5v-9A3.75 3.75 0 017.5 3.75z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM16.5 7.5h.008v.008H16.5V7.5z" />
                </svg>
                {{ __('Pengaturan Instagram') }}
            </x-sidebar-nav-link>
            <x-sidebar-nav-link :href="route('admin.settings.push')" :active="request()->routeIs('admin.settings.push')">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                </svg>
                {{ __('Pengaturan Push (Android)') }}
            </x-sidebar-nav-link>
            <x-sidebar-nav-link :href="route('admin.settings.chat-facts')" :active="request()->routeIs('admin.settings.chat-facts')">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                </svg>
                {{ __('Pengetahuan Asisten ULP') }}
            </x-sidebar-nav-link>

            <p class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('Pengguna & Hak Akses') }}</p>
            <x-sidebar-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                </svg>
                {{ __('Pengguna') }}
            </x-sidebar-nav-link>
            <x-sidebar-nav-link :href="route('admin.roles.index')" :active="request()->routeIs('admin.roles.*')">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                </svg>
                {{ __('Peran & Izin') }}
            </x-sidebar-nav-link>
        @endrole
    </nav>

    <div class="shrink-0 border-t border-gray-100 p-3">
        @isset($aiAutoState)
            <x-ai-status-badge :state="$aiAutoState" :reason="$aiAutoReason" :show-reason="false" class="mb-3 w-full items-center" />
        @endisset

        <x-dropdown width="56" direction="up">
            <x-slot name="trigger">
                <button class="flex w-full items-center gap-2 rounded-lg p-2 text-left hover:bg-gray-100">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-100 text-xs font-semibold text-sky-700">
                        {{ Str::of(Auth::user()->name)->substr(0, 2)->upper() }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-gray-800">{{ Auth::user()->name }}</span>
                        <span class="block truncate text-xs text-gray-500">{{ Auth::user()->email }}</span>
                    </span>
                </button>
            </x-slot>

            <x-slot name="content">
                <x-dropdown-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-dropdown-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-dropdown-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-dropdown-link>
                </form>
            </x-slot>
        </x-dropdown>
    </div>
</aside>
