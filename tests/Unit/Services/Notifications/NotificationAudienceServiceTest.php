<?php

namespace Tests\Unit\Services\Notifications;

use App\Models\Report;
use App\Models\ReportAssignment;
use App\Models\User;
use App\Services\Notifications\NotificationAudienceService;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationAudienceServiceTest extends TestCase
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

    public function test_superuser_is_always_included(): void
    {
        $this->seed(RolesTableSeeder::class);
        $superuser = User::factory()->create();
        $superuser->assignRole('superuser');

        $report = $this->report('baru_masuk');

        $audience = app(NotificationAudienceService::class)->usersVisibleToReport($report);

        $this->assertTrue($audience->contains('id', $superuser->id));
    }

    public function test_user_without_matching_status_permission_is_excluded(): void
    {
        $this->seed(RolesTableSeeder::class);
        $pelaksana = User::factory()->create();
        $pelaksana->assignRole('Pelaksana'); // only has dalam_penanganan

        $report = $this->report('baru_masuk');

        $audience = app(NotificationAudienceService::class)->usersVisibleToReport($report);

        $this->assertFalse($audience->contains('id', $pelaksana->id));
    }

    public function test_user_with_matching_status_permission_is_included(): void
    {
        $this->seed(RolesTableSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole('Administrator'); // has all status permissions

        $report = $this->report('baru_masuk');

        $audience = app(NotificationAudienceService::class)->usersVisibleToReport($report);

        $this->assertTrue($audience->contains('id', $administrator->id));
    }

    public function test_assigned_only_user_is_excluded_when_not_assigned(): void
    {
        $this->seed(RolesTableSeeder::class);
        $pelaksana = User::factory()->create();
        $pelaksana->assignRole('Pelaksana');

        $report = $this->report('dalam_penanganan');

        $audience = app(NotificationAudienceService::class)->usersVisibleToReport($report);

        $this->assertFalse($audience->contains('id', $pelaksana->id));
    }

    public function test_assigned_only_user_is_included_when_assigned_to_the_report(): void
    {
        $this->seed(RolesTableSeeder::class);
        $pelaksana = User::factory()->create();
        $pelaksana->assignRole('Pelaksana');

        $report = $this->report('dalam_penanganan');
        ReportAssignment::create([
            'report_id' => $report->id,
            'assigned_to' => $pelaksana->id,
            'assigned_by' => $pelaksana->id,
            'assigned_at' => now(),
        ]);

        $audience = app(NotificationAudienceService::class)->usersVisibleToReport($report);

        $this->assertTrue($audience->contains('id', $pelaksana->id));
    }
}
