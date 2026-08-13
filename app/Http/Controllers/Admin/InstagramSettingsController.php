<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateInstagramSettingsRequest;
use App\Services\InstagramSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InstagramSettingsController extends Controller
{
    public function __construct(private readonly InstagramSettingsService $settings)
    {
    }

    public function edit(): View
    {
        return view('admin.settings.instagram', [
            'businessAccountId' => $this->settings->businessAccountId(),
            'maskedAccessToken' => $this->settings->maskedAccessToken(),
            'webhookVerifyToken' => $this->settings->webhookVerifyToken(),
            'isConfigured' => $this->settings->isConfigured(),
        ]);
    }

    public function update(UpdateInstagramSettingsRequest $request): RedirectResponse
    {
        $this->settings->save($request->validated(), $request->user());

        return back()->with('status', 'Pengaturan Instagram berhasil disimpan.');
    }
}
