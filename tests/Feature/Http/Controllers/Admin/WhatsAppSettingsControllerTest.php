<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\User;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppSettingsControllerTest extends TestCase
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
            ->get(route('admin.settings.whatsapp'))
            ->assertForbidden();
    }

    public function test_superuser_can_view_the_settings_page(): void
    {
        $this->actingAs($this->superuser())
            ->get(route('admin.settings.whatsapp'))
            ->assertOk()
            ->assertSee('Pengaturan WhatsApp');
    }

    public function test_superuser_can_save_settings(): void
    {
        $this->actingAs($this->superuser())
            ->put(route('admin.settings.whatsapp.update'), [
                'phone_number_id' => '109876543210987',
                'access_token' => 'EAABsecrettoken1234',
                'webhook_verify_token' => 'my-verify-token',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('settings', ['key' => 'wa_phone_number_id']);
    }

    public function test_non_superuser_cannot_save_settings(): void
    {
        $this->actingAs($this->administrator())
            ->put(route('admin.settings.whatsapp.update'), [
                'phone_number_id' => '109876543210987',
                'access_token' => 'EAABsecrettoken1234',
                'webhook_verify_token' => 'my-verify-token',
            ])
            ->assertForbidden();
    }
}
