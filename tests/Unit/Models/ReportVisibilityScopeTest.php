<?php

namespace Tests\Unit\Models;

use App\Models\Report;
use App\Models\ReportAssignment;
use App\Models\User;
use App\Support\ReportPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportVisibilityScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (ReportPermissions::all() as $name => $label) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'superuser', 'guard_name' => 'web']);
    }

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

    public function test_superuser_sees_every_status_regardless_of_permissions(): void
    {
        $superuser = User::factory()->create();
        $superuser->assignRole('superuser');

        $this->report('baru_masuk');
        $this->report('selesai');

        $this->assertSame(2, Report::query()->visibleTo($superuser)->count());
    }

    public function test_role_with_no_permissions_sees_nothing(): void
    {
        $user = User::factory()->create();

        $this->report('baru_masuk');
        $this->report('selesai');

        $this->assertSame(0, Report::query()->visibleTo($user)->count());
    }

    public function test_user_sees_only_the_statuses_they_have_permission_for(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(ReportPermissions::statusPermission('selesai'));

        $this->report('baru_masuk');
        $selesai = $this->report('selesai');

        $visible = Report::query()->visibleTo($user)->get();

        $this->assertCount(1, $visible);
        $this->assertTrue($visible->contains('id', $selesai->id));
    }

    public function test_assigned_only_permission_narrows_visibility_to_own_assignments(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            ReportPermissions::statusPermission('dalam_penanganan'),
            ReportPermissions::ASSIGNED_ONLY,
        ]);

        $ownReport = $this->report('dalam_penanganan');
        $othersReport = $this->report('dalam_penanganan');

        ReportAssignment::create([
            'report_id' => $ownReport->id,
            'assigned_to' => $user->id,
            'assigned_by' => $user->id,
            'assigned_at' => now(),
        ]);
        ReportAssignment::create([
            'report_id' => $othersReport->id,
            'assigned_to' => User::factory()->create()->id,
            'assigned_by' => $user->id,
            'assigned_at' => now(),
        ]);

        $visible = Report::query()->visibleTo($user)->get();

        $this->assertCount(1, $visible);
        $this->assertTrue($visible->contains('id', $ownReport->id));
    }

    public function test_without_assigned_only_permission_sees_all_reports_in_allowed_status(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(ReportPermissions::statusPermission('dalam_penanganan'));

        $this->report('dalam_penanganan');
        $unassigned = $this->report('dalam_penanganan');

        // Not assigned to anyone, and the user has no assigned-saja restriction —
        // it must still be visible.
        $visible = Report::query()->visibleTo($user)->get();

        $this->assertCount(2, $visible);
        $this->assertTrue($visible->contains('id', $unassigned->id));
    }
}
