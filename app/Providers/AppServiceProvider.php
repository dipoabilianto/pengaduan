<?php

namespace App\Providers;

use App\Models\ChatTicket;
use App\Models\Report;
use App\Services\AiHealthService;
use App\Services\BrandingSettingsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Belum ada RouteServiceProvider terpisah di project Laravel 11+ ini — limiter
        // kustom pertama, jadi didaftarkan di sini. Per-tiket (bukan cuma per-IP) supaya
        // satu tiket tidak bisa dibanjiri dari banyak IP berbeda oleh nomor HP yang sama.
        RateLimiter::for('chat-message', function (Request $request) {
            $ticket = $request->route('chatTicket');

            return Limit::perMinute(12)->by($ticket?->id ?? $request->ip());
        });

        View::composer('layouts.sidebar', function ($view) {
            $user = auth()->user();

            // Role apa pun boleh — daftar nama role tidak lagi di-hardcode di sini
            // sejak role/izin jadi dinamis (dibuat/diatur bebas lewat UI Peran & Izin).
            // Setiap user panel admin pasti sudah punya role begitu dibuat lewat UI
            // Pengguna, jadi ini murni jaga-jaga untuk user yang belum punya role sama sekali.
            if (! $user || $user->roles->isEmpty()) {
                return;
            }

            $status = app(AiHealthService::class)->status();

            $view->with('aiAutoState', $status['state']);
            $view->with('aiAutoReason', $status['reason']);

            if ($user->can('viewAny', Report::class)) {
                $view->with('reportStatusCounts', Report::countsByStatus($user));
                $view->with('visibleReportStatuses', Report::visibleStatusesFor($user));

                // Di halaman detail laporan URL tidak selalu punya query string "status" yang
                // akurat (link ke detail membawa status filter dari daftar sebelumnya, dan itu
                // jadi basi begitu admin mengubah status laporan lalu redirect back() ke URL yang
                // sama) — jadi highlight sidebar di halaman ini dituntun oleh status laporan yang
                // SEDANG DILIHAT, bukan query string.
                if (request()->routeIs('admin.reports.show')) {
                    $report = request()->route('report');
                    $view->with('currentReportStatus', $report instanceof Report ? $report->status : null);
                }
            }

            if ($user->can('viewAny', ChatTicket::class)) {
                $view->with('chatWaitingCount', ChatTicket::where('status', ChatTicket::STATUS_MENUNGGU)->count());
            }
        });

        // Logo & favicon bisa diganti kapan saja lewat Pengaturan (Superuser), jadi
        // dibagikan lewat composer alih-alih di-hardcode di tiap layout.
        View::composer(
            ['layouts.app', 'layouts.guest', 'layouts.sidebar', 'components.layouts.public'],
            function ($view) {
                $branding = app(BrandingSettingsService::class);

                $view->with('logoUrl', $branding->logoUrl());
                $view->with('faviconUrl', $branding->faviconUrl());
            }
        );
    }
}
