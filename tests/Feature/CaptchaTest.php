<?php

namespace Tests\Feature;

use Tests\TestCase;

class CaptchaTest extends TestCase
{
    public function test_captcha_returns_a_five_character_code(): void
    {
        $response = $this->get(route('captcha'));

        $response->assertOk();
        $response->assertJsonStructure(['code']);
        $this->assertMatchesRegularExpression('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{5}$/', $response->json('code'));
    }

    public function test_captcha_code_is_stored_in_session_and_matches_the_response(): void
    {
        $response = $this->get(route('captcha'));

        $this->assertSame($response->json('code'), session('captcha'));
    }
}
