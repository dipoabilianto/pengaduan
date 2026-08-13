<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('superuser');
    }

    public function rules(): array
    {
        return [
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            // Bukan svg/ico: favicon selalu digambar ulang jadi PNG persegi lewat
            // Intervention Image (BrandingSettingsService::storeSquaredFavicon), dan
            // driver GD tidak bisa mendekode SVG/ICO sebagai sumber gambar.
            'favicon' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,gif,bmp', 'max:2048'],
        ];
    }
}
