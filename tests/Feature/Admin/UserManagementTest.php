<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function superuser(): User
    {
        $this->seed(RolesTableSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('superuser');

        return $user;
    }

    public function test_non_superuser_cannot_access_user_management(): void
    {
        $this->seed(RolesTableSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Administrator');

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_superuser_can_view_the_users_list(): void
    {
        $this->actingAs($this->superuser())
            ->get(route('admin.users.index'))
            ->assertOk();
    }

    public function test_superuser_can_create_a_user_with_a_role(): void
    {
        $superuser = $this->superuser();

        $response = $this->actingAs($superuser)->post(route('admin.users.store'), [
            'name' => 'Budi Santoso',
            'email' => 'budi@sidumas.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'Pelaksana',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $created = User::where('email', 'budi@sidumas.test')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole('Pelaksana'));
    }

    public function test_superuser_can_update_a_users_role(): void
    {
        $superuser = $this->superuser();
        $user = User::factory()->create();
        $user->assignRole('Pelaksana');

        $this->actingAs($superuser)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'Administrator',
        ])->assertRedirect();

        $this->assertTrue($user->fresh()->hasRole('Administrator'));
        $this->assertFalse($user->fresh()->hasRole('Pelaksana'));
    }

    public function test_superuser_cannot_delete_their_own_account(): void
    {
        $superuser = $this->superuser();

        $this->actingAs($superuser)->delete(route('admin.users.destroy', $superuser))
            ->assertRedirect();

        $this->assertNotNull($superuser->fresh());
    }

    public function test_last_remaining_superuser_cannot_demote_themselves(): void
    {
        $superuser = $this->superuser();

        $this->actingAs($superuser)->put(route('admin.users.update', $superuser), [
            'name' => $superuser->name,
            'email' => $superuser->email,
            'role' => 'Administrator',
        ])->assertSessionHasErrors('role');

        $this->assertTrue($superuser->fresh()->hasRole('superuser'));
    }

    public function test_a_superuser_can_be_demoted_when_another_superuser_remains(): void
    {
        $superuser = $this->superuser();
        $otherSuperuser = $this->superuser();

        $this->actingAs($superuser)->put(route('admin.users.update', $otherSuperuser), [
            'name' => $otherSuperuser->name,
            'email' => $otherSuperuser->email,
            'role' => 'Administrator',
        ])->assertSessionDoesntHaveErrors();

        $this->assertTrue($otherSuperuser->fresh()->hasRole('Administrator'));
    }

    public function test_superuser_can_delete_a_non_superuser_user(): void
    {
        $superuser = $this->superuser();
        $user = User::factory()->create();
        $user->assignRole('Pelaksana');

        $this->actingAs($superuser)->delete(route('admin.users.destroy', $user))
            ->assertRedirect();

        $this->assertNull($user->fresh());
    }
}
