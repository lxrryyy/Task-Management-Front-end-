<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form class="flex flex-col gap-4 w-full" method="POST" action="{{ route('login') }}">
        @csrf

        <div class="flex justify-center items-center">
            <h1 class="text-3xl">Log in</h1>
        </div>


    <div class="mt-6">
        <x-text-input
            id="username"
            class="block w-full px-4 py-3 border border-gray-300 rounded-lg text-gray-700 placeholder-gray-400 focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
            type="text"
            name="username"
            placeholder="Enter your username"
            required
            autofocus
            autocomplete="username"
        />
        <x-input-error :messages="$errors->get('username')" class="mt-1" />
    </div>

    <div class="mt-4 relative">
        <x-text-input
            id="password"
            class="block w-full px-4 py-3 border border-gray-300 rounded-lg text-gray-700 placeholder-gray-400 focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none pr-10"
            type="password"
            name="password"
            placeholder="Enter your password"
            required
            autocomplete="current-password"
        />
        <!-- Eye icon (use heroicons or any SVG) -->
        <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
        </button>
        <x-input-error :messages="$errors->get('password')" class="mt-1" />
    </div>

        <!-- Remember Me -->
        <div class="flex justify-between gap-4 mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <x-primary-button class="
        relative
        overflow-hidden
        items-center justify-center
        ms-3
        bg-white
        text-gray-900
        border-2 border-[#101828]
        transition-all duration-500 ease-out
        hover:text-white
        hover:shadow-lg
        ">
        <span class="
            absolute inset-0
            bg-[#101828]
            w-0
            transition-all duration-500 ease-out
            origin-left
            hover:w-full
        "></span>
            {{ __('Log in') }}
            </x-primary-button>

    </form>
</x-guest-layout>
