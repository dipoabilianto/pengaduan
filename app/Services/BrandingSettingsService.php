<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Color;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Logo & favicon sistem — disimpan sebagai file di disk "public" (bukan hardcoded
 * asset), path-nya dicatat lewat Setting key/value agar Superuser bisa mengganti
 * kapan saja lewat halaman Pengaturan tanpa perlu deploy ulang kode.
 */
class BrandingSettingsService
{
    private const LOGO_KEY = 'branding_logo_path';

    private const FAVICON_KEY = 'branding_favicon_path';

    /**
     * <link rel="icon"> tidak pernah melewati CSS object-fit — browser/OS yang
     * menampilkannya sendiri yang memutuskan cara menyesuaikan gambar non-persegi,
     * dan yang paling sering terjadi adalah crop tengah (bukan diperkecil utuh).
     * Supaya logo instansi (mis. perisai vertikal) tidak pernah terpotong, favicon
     * dipaskan ke kanvas persegi di server sebelum disimpan — padding-nya transparan
     * (bukan putih) supaya tidak muncul kotak putih di tab browser bertema gelap.
     */
    private const FAVICON_SIZE = 256;

    public function logoUrl(): ?string
    {
        return $this->urlFor(self::LOGO_KEY);
    }

    public function faviconUrl(): ?string
    {
        return $this->urlFor(self::FAVICON_KEY);
    }

    private function urlFor(string $key): ?string
    {
        $path = Setting::get($key);

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function save(?UploadedFile $logo, ?UploadedFile $favicon, ?int $updatedBy = null): void
    {
        if ($logo) {
            $this->replace(self::LOGO_KEY, $logo->store('branding', 'public'), $updatedBy);
        }

        if ($favicon) {
            $this->replace(self::FAVICON_KEY, $this->storeSquaredFavicon($favicon), $updatedBy);
        }
    }

    private function storeSquaredFavicon(UploadedFile $file): string
    {
        $manager = new ImageManager(Driver::class);

        $image = $manager->decode($file->getRealPath())
            ->contain(self::FAVICON_SIZE, self::FAVICON_SIZE, Color::transparent());

        $path = 'branding/'.Str::uuid().'.png';

        Storage::disk('public')->put($path, (string) $image->encodeUsingFileExtension('png'));

        return $path;
    }

    private function replace(string $key, string $newPath, ?int $updatedBy): void
    {
        $oldPath = Setting::get($key);

        Setting::put($key, $newPath, $updatedBy);

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }
    }
}
