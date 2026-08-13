<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Bab 6.1 PDR: "Aplikasi companion Android untuk Admin & Pejabat Berwenang (bukan untuk
 * publik/pelapor)" — login di sini memakai akun staf yang sama persis dengan web
 * (tabel users, role/permission yang sama), bukan tipe akun baru.
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            return response()->json(['message' => 'Email atau kata sandi salah.'], 401);
        }

        $token = $user->createToken($request->validated('device_name') ?? 'android-device')->plainTextToken;

        return response()->json(['token' => $token]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil logout.']);
    }
}
