<?php

namespace App\Services\Ai;

readonly class AiAssessmentResult
{
    /**
     * @param  int  $score  0-100 confidence/severity score
     * @param  string  $flag  one of: red_code, tinggi, sedang, rendah
     * @param  array<string,mixed>  $raw  full raw response for audit/debugging (Bab 9: report_ai_assessments.ai_raw_response)
     * @param  string  $reasoning  short rationale from the model, shown to Admin during human-in-the-loop review
     */
    public function __construct(
        public int $score,
        public string $flag,
        public array $raw,
        public string $reasoning = '',
    ) {
    }
}
