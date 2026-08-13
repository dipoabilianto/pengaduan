<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class QueueHealthService
{
    /**
     * A pending job left unprocessed longer than this means whatever should be
     * consuming the queue (the queue:work worker) isn't running — the AI scoring
     * pipeline depends on it, and that failure is otherwise silent to Admin.
     */
    private const STUCK_THRESHOLD_SECONDS = 120;

    public function oldestPendingJobAgeSeconds(): ?int
    {
        $oldest = DB::table('jobs')->min('created_at');

        return $oldest ? now()->timestamp - $oldest : null;
    }

    public function isStuck(): bool
    {
        return ($this->oldestPendingJobAgeSeconds() ?? 0) > self::STUCK_THRESHOLD_SECONDS;
    }
}
