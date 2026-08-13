<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\User;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function superuser(): User
    {
        $this->seed(RolesTableSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('superuser');

        return $user;
    }

    private function administrator(): User
    {
        $this->seed(RolesTableSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Administrator');

        return $user;
    }

    public function test_non_superuser_cannot_view_the_settings_page(): void
    {
        $this->actingAs($this->administrator())
            ->get(route('admin.settings.push'))
            ->assertForbidden();
    }

    public function test_superuser_can_view_the_settings_page(): void
    {
        $this->actingAs($this->superuser())
            ->get(route('admin.settings.push'))
            ->assertOk()
            ->assertSee('Pengaturan Notifikasi Push');
    }

    public function test_superuser_can_save_settings(): void
    {
        $this->actingAs($this->superuser())
            ->put(route('admin.settings.push.update'), [
                'project_id' => 'sidumas-tubaba',
                'service_account_json' => json_encode(['client_email' => 'a@b.iam.gserviceaccount.com', 'private_key' => 'x']),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('settings', ['key' => 'fcm_project_id']);
    }

    public function test_rejects_invalid_json(): void
    {
        $this->actingAs($this->superuser())
            ->put(route('admin.settings.push.update'), [
                'project_id' => 'sidumas-tubaba',
                'service_account_json' => 'not-json',
            ])
            ->assertSessionHasErrors('service_account_json');
    }
}
