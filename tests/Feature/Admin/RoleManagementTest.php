<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\ReportPermissions;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private function superuser(): User
    {
        $this->seed(RolesTableSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('superuser');

        return $user;
    }

    public function test_non_superuser_cannot_access_role_management(): void
    {
        $this->seed(RolesTableSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Administrator');

        $this->actingAs($user)->get(route('admin.roles.index'))->assertForbidden();
    }

    public function test_superuser_can_create_a_role_with_selected_statuses_and_abilities(): void
    {
        $superuser = $this->superuser();

        $this->actingAs($superuser)->post(route('admin.roles.store'), [
            'name' => 'Verifikator',
            'abilities' => ['laporan.ubah-status'],
            'assigned_only' => '1',
            'statuses' => ['baru_masuk', 'terverifikasi_admin'],
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::where('name', 'Verifikator')->first();
        $this->assertNotNull($role);
        $this->assertTrue($role->hasPermissionTo('laporan.ubah-status'));
        $this->assertTrue($role->hasPermissionTo(ReportPermissions::ASSIGNED_ONLY));
        $this->assertTrue($role->hasPermissionTo(ReportPermissions::statusPermission('baru_masuk')));
        $this->assertTrue($role->hasPermissionTo(ReportPermissions::statusPermission('terverifikasi_admin')));
        $this->assertFalse($role->hasPermissionTo(ReportPermissions::statusPermission('selesai')));
    }

    public function test_a_role_can_be_created_with_no_permissions_at_all(): void
    {
        $superuser = $this->superuser();

        $this->actingAs($superuser)->post(route('admin.roles.store'), [
            'name' => 'Kosong Dulu',
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::where('name', 'Kosong Dulu')->first();
        $this->assertNotNull($role);
        $this->assertCount(0, $role->permissions);
    }

    public function test_role_name_superuser_is_rejected_case_insensitively(): void
    {
        $superuser = $this->superuser();

        $this->actingAs($superuser)->post(route('admin.roles.store'), [
            'name' => 'SuperUser',
        ])->assertSessionHasErrors('name');

        $this->assertSame(1, Role::where('name', 'like', '%uperuser%')->count());
    }

    public function test_the_superuser_role_cannot_be_edited(): void
    {
        $superuser = $this->superuser();
        $superuserRole = Role::where('name', 'superuser')->first();

        $this->actingAs($superuser)->get(route('admin.roles.edit', $superuserRole))->assertForbidden();
        $this->actingAs($superuser)->put(route('admin.roles.update', $superuserRole), ['name' => 'Diubah'])->assertForbidden();
    }

    public function test_the_superuser_role_cannot_be_deleted(): void
    {
        $superuser = $this->superuser();
        $superuserRole = Role::where('name', 'superuser')->first();

        $this->actingAs($superuser)->delete(route('admin.roles.destroy', $superuserRole))->assertForbidden();
        $this->assertNotNull($superuserRole->fresh());
    }

    public function test_a_role_still_in_use_cannot_be_deleted(): void
    {
        $superuser = $this->superuser();
        $user = User::factory()->create();
        $user->assignRole('Pelaksana');

        $pelaksana = Role::where('name', 'Pelaksana')->first();

        $this->actingAs($superuser)->delete(route('admin.roles.destroy', $pelaksana))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotNull($pelaksana->fresh());
    }

    public function test_an_unused_role_can_be_deleted(): void
    {
        $superuser = $this->superuser();
        $pengawas = Role::where('name', 'Pengawas')->first();

        $this->actingAs($superuser)->delete(route('admin.roles.destroy', $pengawas))
            ->assertRedirect(route('admin.roles.index'));

        $this->assertNull($pengawas->fresh());
    }

    public function test_chat_abilities_round_trip_through_the_role_form(): void
    {
        $superuser = $this->superuser();

        $this->actingAs($superuser)->post(route('admin.roles.store'), [
            'name' => 'Petugas Chat',
            'abilities' => ['chat.lihat', 'chat.balas'],
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::where('name', 'Petugas Chat')->first();
        $this->assertTrue($role->hasPermissionTo('chat.lihat'));
        $this->assertTrue($role->hasPermissionTo('chat.balas'));
        $this->assertFalse($role->hasPermissionTo('chat.tutup'));
        $this->assertFalse($role->hasPermissionTo('chat.lihat-nomor-telepon'));
    }
}
