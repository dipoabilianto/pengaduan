<x-guest-layout>
    <h1 class="mb-1 text-lg font-semibold text-gray-800">Masuk ke Panel Petugas</h1>
    <p class="mb-6 text-sm text-gray-500">Khusus petugas &amp; admin Unit Layanan Pengaduan.</p>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <!-- Email Address -->
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="mt-1 block w-full rounded-lg border-gray-300 text-gray-900 shadow-sm focus:border-sky-500 focus:ring-sky-500">
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700">{{ __('Password') }}</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="mt-1 block w-full rounded-lg border-gray-300 text-gray-900 shadow-sm focus:border-sky-500 focus:ring-sky-500">
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-sky-600 shadow-sm focus:ring-sky-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-between pt-2">
            @if (Route::has('password.request'))
                <a class="rounded-md text-sm text-gray-500 underline hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @else
                <span></span>
            @endif

            <button type="submit" class="inline-flex items-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2">
                {{ __('Log in') }}
            </button>
        </div>
    </form>
</x-guest-layout>
