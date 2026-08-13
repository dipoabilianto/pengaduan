<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterDeviceTokenRequest;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(RegisterDeviceTokenRequest $request): JsonResponse
    {
        $data = $request->validated();

        $deviceToken = DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'] ?? 'android',
                'device_name' => $data['device_name'] ?? null,
                'last_used_at' => now(),
            ],
        );

        return response()->json(['id' => $deviceToken->id], 201);
    }

    public function destroy(Request $request, DeviceToken $deviceToken): JsonResponse
    {
        if ($deviceToken->user_id !== $request->user()->id) {
            abort(403);
        }

        $deviceToken->delete();

        return response()->json(status: 204);
    }
}
