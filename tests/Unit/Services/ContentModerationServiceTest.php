<?php

namespace Tests\Unit\Services;

use App\Services\ContentModerationService;
use PHPUnit\Framework\TestCase;

class ContentModerationServiceTest extends TestCase
{
    public function test_normal_text_is_not_flagged(): void
    {
        $result = (new ContentModerationService)->check('Selamat pagi, saya mau tanya syarat bikin KTP.');

        $this->assertFalse($result['flagged']);
        $this->assertNull($result['reason']);
    }

    public function test_profanity_is_flagged(): void
    {
        $result = (new ContentModerationService)->check('Dasar anjing, petugasnya lambat sekali.');

        $this->assertTrue($result['flagged']);
        $this->assertNotNull($result['reason']);
    }

    public function test_profanity_is_case_insensitive(): void
    {
        $result = (new ContentModerationService)->check('KAMU GOBLOK BANGET SIH');

        $this->assertTrue($result['flagged']);
    }

    public function test_catches_simple_space_obfuscation(): void
    {
        $result = (new ContentModerationService)->check('dasar b a n g s a t kamu');

        $this->assertTrue($result['flagged']);
    }

    public function test_harassment_phrase_is_flagged(): void
    {
        $result = (new ContentModerationService)->check('kirim foto bugil dong kak, mau lihat');

        $this->assertTrue($result['flagged']);
    }

    public function test_short_innocent_word_is_not_falsely_flagged_by_substring(): void
    {
        // "asu" is in PROFANITY, but must not fire just because it's a substring of an
        // unrelated, innocent word — word-boundary matching should protect this.
        $result = (new ContentModerationService)->check('Anak saya diasuh oleh neneknya di kampung.');

        $this->assertFalse($result['flagged']);
    }
}
