<?php

namespace Tests\Feature\Public;

use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicReportListTest extends TestCase
{
    use RefreshDatabase;

    private function report(array $attributes = []): Report
    {
        return Report::create(array_merge([
            'ticket_no' => 'TCK-'.fake()->unique()->numerify('####'),
            'type' => 'pengaduan',
            'category' => 'Pelayanan Administrasi Kependudukan',
            'channel' => 'web',
            'status' => 'dalam_penanganan',
            'what' => 'Contoh kronologi kejadian untuk pengujian.',
        ], $attributes));
    }

    public function test_shows_the_report_content_in_the_public_list(): void
    {
        $this->report(['what' => 'Pelayanan sangat lambat di loket 3.']);

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('Pelayanan sangat lambat di loket 3.');
    }

    public function test_shows_the_officer_reply_in_the_public_list_when_present(): void
    {
        $this->report([
            'public_reply' => 'Sudah kami tindak lanjuti ke bidang terkait.',
            'public_reply_at' => now(),
        ]);

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('Balasan Petugas');
        $response->assertSee('Sudah kami tindak lanjuti ke bidang terkait.');
    }

    public function test_hides_the_reply_section_when_no_reply_saved_yet(): void
    {
        $this->report();

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertDontSee('Balasan Petugas');
    }

    public function test_redacts_personal_data_typed_into_what_before_public_display(): void
    {
        $this->report(['what' => 'Hubungi saya di 081234567890 kalau ada info lebih lanjut.']);

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertDontSee('081234567890');
        $response->assertSee('***');
    }

    public function test_redacts_personal_data_in_the_officer_reply_before_public_display(): void
    {
        $this->report([
            'public_reply' => 'Silakan hubungi 081234567890 untuk informasi lanjutan.',
            'public_reply_at' => now(),
        ]);

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertDontSee('081234567890');
    }

    public function test_truncates_a_long_what_in_the_list(): void
    {
        $this->report(['what' => str_repeat('Uraian laporan yang sangat panjang. ', 20)]);

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('...', false);
    }

    public function test_still_excludes_red_code_reports_from_the_public_list(): void
    {
        $this->report(['what' => 'RAHASIA KODE MERAH TIDAK BOLEH TAMPIL', 'urgency_flag' => 'red_code']);

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertDontSee('RAHASIA KODE MERAH TIDAK BOLEH TAMPIL');
    }

    /**
     * Regression test: the public "Total Laporan Masuk" / "Selesai" / "Dalam Proses"
     * summary stats used to be computed from ALL reports (including Red Code), while the
     * report list/chart further down the same page excluded Red Code — so the moment a
     * Red Code report existed, the headline total would be higher than what the list/chart
     * below it actually showed. Both must now come from the same publiclyVisible() scope.
     */
    public function test_summary_stats_exclude_red_code_reports_just_like_the_list_and_chart(): void
    {
        $this->report(['status' => 'selesai']);
        $this->report(['status' => 'dalam_penanganan', 'urgency_flag' => 'red_code']);

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertViewHas('stats', fn ($stats) => $stats['total'] === 1 && $stats['selesai'] === 1);
        $response->assertViewHas('publicTotal', 1);
    }

    /**
     * Regression test: the public "Dalam Proses" stat used to count "Baru Masuk" (not yet
     * verified) reports too, via whereNotIn(['selesai', 'ditolak']) — but the admin
     * dashboard's "Dalam Proses" explicitly excludes Baru Masuk (it's its own separate
     * category there). Same label, same underlying data, must mean the same thing on both
     * sides — otherwise the two dashboards visibly disagree for identical data.
     */
    public function test_dalam_proses_excludes_baru_masuk_reports_to_match_the_admin_dashboard_definition(): void
    {
        $this->report(['status' => 'baru_masuk']);
        $this->report(['status' => 'terverifikasi_admin', 'urgency_flag' => 'sedang']);

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertViewHas('stats', fn ($stats) => $stats['dalam_proses'] === 1);
    }

    /**
     * Regression test for the "flicker" reported when clicking Filter/pagination on the
     * public report list: a plain GET form submit triggers a FULL page reload, which resets
     * scroll to the top (replaying every AOS entrance animation across the whole page) right
     * before jumping back down to #daftar-pengaduan. The filter form now submits via fetch()
     * with an X-Requested-With header, and this endpoint must respond with ONLY the list
     * fragment (no full page, no stats/charts markup) so the swap has nothing else to reload.
     */
    public function test_ajax_filter_request_returns_only_the_list_fragment(): void
    {
        $this->report(['category' => 'Pelayanan Administrasi Kependudukan']);

        $response = $this->get(route('public.home'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $response->assertSee('Contoh kronologi kejadian untuk pengujian.');
        // The full page's hero/stats/chart sections must be absent from the fragment.
        $response->assertDontSee('Dashboard Statistik Publik');
        $response->assertDontSee('Total Laporan Masuk');
        $response->assertDontSee('KATA-KITA');
    }

    public function test_ajax_filter_request_still_respects_the_status_and_category_filters(): void
    {
        $this->report(['category' => 'Pelayanan Administrasi Kependudukan', 'status' => 'selesai']);
        $this->report(['category' => 'Perilaku/Sikap Petugas', 'status' => 'dalam_penanganan', 'urgency_flag' => 'sedang']);

        $response = $this->get(route('public.home', ['status' => 'selesai']), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $response->assertSee('Pelayanan Administrasi Kependudukan');
        $response->assertDontSee('Perilaku/Sikap Petugas');
    }
}
