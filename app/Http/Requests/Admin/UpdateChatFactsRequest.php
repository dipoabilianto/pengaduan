<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChatFactsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('superuser');
    }

    public function rules(): array
    {
        // settings.value is a TEXT column (~65.535 bytes) storing the ENCRYPTED value —
        // Setting's 'encrypted' cast adds ~35-40% overhead (base64 + AES-CBC + JSON
        // envelope), so 20.000 plaintext characters (~27-30k encrypted bytes) still leaves
        // comfortable headroom, unlike the old 8.000 cap which was too tight for a full
        // ULP knowledge base covering every Disdukcapil service.
        return [
            'facts' => ['required', 'string', 'max:20000'],
        ];
    }
}
