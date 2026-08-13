<?php

namespace Tests\Feature\Services;

use App\Services\QueueHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueueHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    private function insertJob(int $createdAt): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'available_at' => $createdAt,
            'created_at' => $createdAt,
        ]);
    }

    public function test_is_not_stuck_when_queue_is_empty(): void
    {
        $this->assertFalse(app(QueueHealthService::class)->isStuck());
    }

    public function test_is_not_stuck_when_oldest_job_is_recent(): void
    {
        $this->insertJob(now()->subSeconds(10)->timestamp);

        $this->assertFalse(app(QueueHealthService::class)->isStuck());
    }

    public function test_is_stuck_when_oldest_job_is_older_than_threshold(): void
    {
        $this->insertJob(now()->subMinutes(5)->timestamp);

        $this->assertTrue(app(QueueHealthService::class)->isStuck());
    }

    public function test_oldest_pending_job_age_seconds_is_null_when_empty(): void
    {
        $this->assertNull(app(QueueHealthService::class)->oldestPendingJobAgeSeconds());
    }
}
