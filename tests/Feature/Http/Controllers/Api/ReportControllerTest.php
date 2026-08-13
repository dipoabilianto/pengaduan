<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Report;
use App\Models\User;
use App\Support\ReportPermissions;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function report(string $status): Report
    {
        return Report::create([
            'ticket_no' => 'TCK-'.fake()->unique()->numerify('####'),
            'type' => 'pengaduan',
            'category' => 'Pelayanan Administrasi Kependudukan',
            'channel' => 'web',
            'status' => $status,
            'what' => 'isi laporan',
        ]);
    }

    public function test_index_only_returns_reports_visible_to_the_authenticated_user(): void
    {
        $this->seed(RolesTableSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo(ReportPermissions::statusPermission('selesai'));

        $this->report('baru_masuk');
        $selesai = $this->report('selesai');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/reports')->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($selesai->id));
        $this->assertCount(1, $ids);
    }

    public function test_index_returns_everything_for_superuser(): void
    {
        $this->seed(RolesTableSeeder::class);
        $superuser = User::factory()->create();
        $superuser->assignRole('superuser');

        $this->report('baru_masuk');
        $this->report('selesai');

        Sanctum::actingAs($superuser);

        $response = $this->getJson('/api/reports')->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_show_does_not_expose_reporter_pii(): void
    {
        $this->seed(RolesTableSeeder::class);
        $superuser = User::factory()->create();
        $superuser->assignRole('superuser');

        $report = $this->report('baru_masuk');

        Sanctum::actingAs($superuser);

        $response = $this->getJson("/api/reports/{$report->id}")->assertOk();

        $response->assertJsonMissingPath('data.reporter');
        $response->assertJsonMissingPath('data.name');
        $response->assertJsonMissingPath('data.phone');
    }

    public function test_update_status_is_denied_the_same_way_the_web_policy_would_deny_it(): void
    {
        $this->seed(RolesTableSeeder::class);
        $user = User::factory()->create();
        // No 'laporan.ubah-status' permission at all.
        $user->givePermissionTo(ReportPermissions::statusPermission('baru_masuk'));

        $report = $this->report('baru_masuk');

        Sanctum::actingAs($user);

        $this->putJson("/api/reports/{$report->id}/status", [
            'status' => 'terverifikasi_admin',
        ])->assertForbidden();
    }

    public function test_update_status_succeeds_through_the_same_service_as_web(): void
    {
        $this->seed(RolesTableSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Administrator');

        $report = $this->report('baru_masuk');

        Sanctum::actingAs($admin);

        $this->putJson("/api/reports/{$report->id}/status", [
            'status' => 'terverifikasi_admin',
            'urgency_flag' => 'sedang',
        ])->assertOk()->assertJsonPath('data.status', 'terverifikasi_admin');

        $this->assertSame('terverifikasi_admin', $report->fresh()->status);
        $this->assertDatabaseHas('report_status_logs', ['report_id' => $report->id, 'new_status' => 'terverifikasi_admin']);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/reports')->assertUnauthorized();
    }
}
