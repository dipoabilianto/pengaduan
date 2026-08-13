<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\ChatFactsService;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatFactsSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function superuser(): User
    {
        $this->seed(RolesTableSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('superuser');

        return $user;
    }

    public function test_non_superuser_cannot_access_the_settings_page(): void
    {
        $this->seed(RolesTableSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Administrator');

        $this->actingAs($user)->get(route('admin.settings.chat-facts'))->assertForbidden();
    }

    public function test_superuser_can_update_the_chat_facts(): void
    {
        $superuser = $this->superuser();

        $this->actingAs($superuser)->put(route('admin.settings.chat-facts.update'), [
            'facts' => 'Syarat perekaman KTP-el: KK asli, sudah 17 tahun/menikah.',
        ])->assertRedirect();

        $this->assertSame('Syarat perekaman KTP-el: KK asli, sudah 17 tahun/menikah.', app(ChatFactsService::class)->get());
    }
}
