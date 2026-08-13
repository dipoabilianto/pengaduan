<?php

namespace Tests\Feature\Console;

use App\Jobs\SendDailySummaryToUserJob;
use App\Models\User;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendDailySummaryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_one_job_per_eligible_user(): void
    {
        Queue::fake();

        $this->seed(RolesTableSeeder::class);

        $administrator = User::factory()->create();
        $administrator->assignRole('Administrator');

        $superuser = User::factory()->create();
        $superuser->assignRole('superuser');

        // No role at all → has zero visible statuses → not eligible.
        $noRole = User::factory()->create();

        $this->artisan('notify:daily-summary')->assertSuccessful();

        Queue::assertPushed(SendDailySummaryToUserJob::class, fn ($job) => $job->user->is($administrator));
        Queue::assertPushed(SendDailySummaryToUserJob::class, fn ($job) => $job->user->is($superuser));
        Queue::assertNotPushed(SendDailySummaryToUserJob::class, fn ($job) => $job->user->is($noRole));
    }
}
