<?php

namespace Tests\Feature\Public;

use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReportWizardStepRestoreTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'pengaduan',
            'category' => Report::CATEGORIES_PENGADUAN[0],
            'phone' => '081234567890',
            'what' => 'Contoh kronologi kejadian untuk pengujian.',
            'g-recaptcha-response' => 'bad-token',
        ], $overrides);
    }

    public function test_wrong_captcha_reopens_the_form_on_the_verification_step_not_step_one(): void
    {
        Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => false])]);

        $store = $this->post(route('report.store'), $this->validPayload());

        $store->assertSessionHasErrors('g-recaptcha-response');
        $store->assertSessionDoesntHaveErrors(['type', 'category', 'phone', 'what']);

        $create = $this->get(route('report.create'));

        $create->assertOk();
        $create->assertSee('step: 5,', false);
        // The earlier answers must still be pre-filled, not just the step position.
        $create->assertSee("phone: '081234567890'", false);
        $create->assertSee("what: 'Contoh kronologi kejadian untuk pengujian.'", false);
    }

    public function test_missing_required_earlier_field_reopens_the_form_on_that_step(): void
    {
        Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => true])]);

        $store = $this->post(route('report.store'), $this->validPayload(['phone' => '', 'g-recaptcha-response' => 'good-token']));

        $store->assertSessionHasErrors('phone');

        $create = $this->get(route('report.create'));

        $create->assertOk();
        $create->assertSee('step: 2,', false);
    }

    public function test_valid_submission_has_no_errors_to_restore(): void
    {
        Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => true])]);

        $store = $this->post(route('report.store'), $this->validPayload(['g-recaptcha-response' => 'good-token']));

        $store->assertSessionHasNoErrors();
        $store->assertRedirect();
    }
}
