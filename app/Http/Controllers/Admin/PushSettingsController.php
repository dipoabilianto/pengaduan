<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePushSettingsRequest;
use App\Services\PushSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PushSettingsController extends Controller
{
    public function __construct(private readonly PushSettingsService $settings)
    {
    }

    public function edit(): View
    {
        return view('admin.settings.push', [
            'projectId' => $this->settings->projectId(),
            'hasServiceAccount' => filled($this->settings->serviceAccountJson()),
            'isConfigured' => $this->settings->isConfigured(),
        ]);
    }

    public function update(UpdatePushSettingsRequest $request): RedirectResponse
    {
        $this->settings->save($request->validated(), $request->user());

        return back()->with('status', 'Pengaturan notifikasi push berhasil disimpan.');
    }
}
