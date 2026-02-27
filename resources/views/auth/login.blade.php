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
                id="email"
                class="block w-full px-4 py-3 border border-gray-300 rounded-lg text-gray-700 placeholder-gray-400 focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
                type="text"
                value="{{ old('email') }}"
                name="email"
                placeholder="Enter your email"
                required
                autofocus
                autocomplete="email"
            />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <div class="mt-4 relative" x-data="{ showPassword: false }">
            <input
                id="password"
                :type="showPassword ? 'text' : 'password'"
                name="password"
                placeholder="Enter your password"
                required
                autocomplete="current-password"
                class="block w-full px-4 py-3 border border-gray-300 rounded-lg text-gray-700 placeholder-gray-400 focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none pr-10"
            />

            <button type="button" @click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none">
                <svg x-show="!showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <svg x-show="showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
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

        <x-primary-button
            class="
                group
                relative
                overflow-hidden
                items-center justify-center
                ms-3
                px-6 py-3
                font-medium
                text-gray-900
                bg-white
                border-2 border-[#101828]
                rounded-lg
                shadow-sm
                transition-colors duration-1000 ease-out
                hover:text-white"
            >
            <span class="
                absolute inset-0
                bg-[#101828]
                w-0
                transition-all duration-1000 ease-out
                origin-right
                group-hover:w-full
            "></span>

            <span class="relative z-10">
                {{ __('Log in') }}
            </span>
        </x-primary-button>

    </form>
</x-guest-layout>
