<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class CaptchaController extends Controller
{
    /**
     * Alfabet 5 huruf/angka acak (Bab 3.1 & 4.1.5), tanpa karakter mirip (0/O, 1/I).
     * Dikirim sebagai teks JSON — dirender sebagai HTML biasa oleh frontend, bukan
     * gambar, supaya legibility tidak pernah bergantung pada rendering font/canvas.
     */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __invoke(): JsonResponse
    {
        $code = collect(range(1, 5))
            ->map(fn () => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)])
            ->implode('');

        session(['captcha' => $code]);

        return response()->json([
            'code' => $code,
        ]);
    }
}
