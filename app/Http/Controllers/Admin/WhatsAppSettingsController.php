<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateWhatsAppSettingsRequest;
use App\Services\WhatsAppSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WhatsAppSettingsController extends Controller
{
    public function __construct(private readonly WhatsAppSettingsService $settings)
    {
    }

    public function edit(): View
    {
        return view('admin.settings.whatsapp', [
            'phoneNumberId' => $this->settings->phoneNumberId(),
            'maskedAccessToken' => $this->settings->maskedAccessToken(),
            'webhookVerifyToken' => $this->settings->webhookVerifyToken(),
            'isConfigured' => $this->settings->isConfigured(),
        ]);
    }

    public function update(UpdateWhatsAppSettingsRequest $request): RedirectResponse
    {
        $this->settings->save($request->validated(), $request->user());

        return back()->with('status', 'Pengaturan WhatsApp berhasil disimpan.');
    }
}
