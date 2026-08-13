<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBrandingSettingsRequest;
use App\Services\BrandingSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BrandingSettingsController extends Controller
{
    public function __construct(private readonly BrandingSettingsService $branding)
    {
    }

    public function edit(): View
    {
        return view('admin.settings.branding', [
            'logoUrl' => $this->branding->logoUrl(),
            'faviconUrl' => $this->branding->faviconUrl(),
        ]);
    }

    public function update(UpdateBrandingSettingsRequest $request): RedirectResponse
    {
        $this->branding->save(
            $request->file('logo'),
            $request->file('favicon'),
            $request->user()->id,
        );

        return back()->with('status', 'Logo & favicon berhasil disimpan.');
    }
}
