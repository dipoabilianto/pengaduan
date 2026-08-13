<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateChatFactsRequest;
use App\Services\ChatFactsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ChatFactsSettingsController extends Controller
{
    public function __construct(private readonly ChatFactsService $facts)
    {
    }

    public function edit(): View
    {
        return view('admin.settings.chat-facts', [
            'facts' => $this->facts->get(),
        ]);
    }

    public function update(UpdateChatFactsRequest $request): RedirectResponse
    {
        $this->facts->save($request->validated('facts'), $request->user()->id);

        return back()->with('status', 'Pengetahuan Asisten ULP berhasil disimpan.');
    }
}
