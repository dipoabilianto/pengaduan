<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceTokenControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/device-tokens', ['token' => 'abc'])
            ->assertUnauthorized();
    }

    public function test_registers_a_new_device_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/device-tokens', ['token' => 'fcm-token-123', 'platform' => 'android'])
            ->assertCreated();

        $this->assertDatabaseHas('device_tokens', ['token' => 'fcm-token-123', 'user_id' => $user->id]);
    }

    public function test_reregistering_the_same_token_upserts_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/device-tokens', ['token' => 'fcm-token-123'])->assertCreated();
        $this->postJson('/api/device-tokens', ['token' => 'fcm-token-123'])->assertCreated();

        $this->assertSame(1, DeviceToken::where('token', 'fcm-token-123')->count());
    }

    public function test_cannot_delete_another_users_device_token(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $deviceToken = DeviceToken::create(['user_id' => $owner->id, 'token' => 'fcm-token-123', 'platform' => 'android']);

        Sanctum::actingAs($intruder);

        $this->deleteJson("/api/device-tokens/{$deviceToken->id}")->assertForbidden();
        $this->assertDatabaseHas('device_tokens', ['id' => $deviceToken->id]);
    }

    public function test_can_delete_own_device_token(): void
    {
        $user = User::factory()->create();
        $deviceToken = DeviceToken::create(['user_id' => $user->id, 'token' => 'fcm-token-123', 'platform' => 'android']);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/device-tokens/{$deviceToken->id}")->assertNoContent();
        $this->assertDatabaseMissing('device_tokens', ['id' => $deviceToken->id]);
    }
}
