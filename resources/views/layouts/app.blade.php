<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=ubuntu:400,500,600&display=swap" rel="stylesheet" />
    {{-- Datepicker for constrained due-date ranges --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    @php
        $useBuildOnNetwork = !app()->environment('production') && !in_array(request()->getHost(), ['localhost', '127.0.0.1'], true);
        $manifestPath = public_path('build/manifest.json');
    @endphp
    @if($useBuildOnNetwork && file_exists($manifestPath))
        @php
            $manifest = json_decode(file_get_contents($manifestPath), true) ?? [];
        @endphp
        @if(!empty($manifest['resources/css/app.css']['file']))
            <link rel="stylesheet" href="{{ asset('build/'.$manifest['resources/css/app.css']['file']) }}">
        @endif
        @if(!empty($manifest['resources/js/app.js']['file']))
            <script type="module" src="{{ asset('build/'.$manifest['resources/js/app.js']['file']) }}"></script>
        @endif
    @else
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body class="font-sans antialiased">

<div class="flex h-screen relative">

    <div class="group absolute left-0 top-0 h-full text-white flex flex-col transition-all duration-300 ease-in-out w-16 hover:w-64 overflow-hidden z-50"
         style="background-color: #102B3C;">

        <div class="flex items-center h-16 px-3 border-b border-white/20">
            <img src="{{ asset('images/icon-white.png') }}" alt="Icon"
                 class="w-8 block group-hover:hidden" />
            <img src="{{ asset('images/odecci-plain-logo.png') }}" alt="Logo"
                 class="w-36 hidden group-hover:block" />
        </div>

        <nav class="flex flex-col p-2 gap-1 mt-4 flex-1">

            <a href="/dashboard"
               class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('dashboard') ? 'focus-clr-accent' : 'text-white' }}">
                <x-icons.dashboard classes="w-6 h-6" />
                <span class="hidden group-hover:block">Dashboard</span>
            </a>

            <a href="/calendar"
               class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('calendar') ? 'focus-clr-accent' : '' }} hover-clr-accent">
                <x-icons.calendar classes="w-6 h-6" />
                <span class="hidden group-hover:block">Calendar</span>
            </a>

            <a href="/projects"
               class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('projects') ? 'focus-clr-accent' : '' }} hover-clr-accent">
                <x-icons.project classes="w-6 h-6" />
                <span class="hidden group-hover:block">Projects</span>
            </a>

            @php
                $navUser = \Illuminate\Support\Facades\Session::get('user', []);
                $navRole = mb_strtolower(trim((string) ($navUser['role'] ?? $navUser['Role'] ?? $navUser['roleName'] ?? $navUser['RoleName'] ?? '')));
            @endphp
            @if($navRole === 'admin')
                <a href="/audit-logs"
                   class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('audit-logs') ? 'focus-clr-accent' : '' }} hover-clr-accent">
                    <x-icons.time-logs classes="w-6 h-6" />
                    <span class="hidden group-hover:block">Audit Logs</span>
                </a>
            @endif
        </nav>

        <div class="px-2 pb-1">
            <a href="/settings"
               class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('settings') ? 'focus-clr-accent' : '' }} hover-clr-accent">
                <x-icons.settings classes="w-6 h-6" />
                <span class="hidden group-hover:block">Settings</span>
            </a>
        </div>

        <div class="p-2 border-t border-white/20" x-data="{ open: false }">
            <button type="button" @click="open = true"
                class="w-full flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap hover-clr-accent">
                <x-icons.logout classes="w-6 h-6" />
                <span class="hidden group-hover:block">Logout</span>
            </button>

            <form x-ref="logoutForm" method="POST" action="{{ route('logout') }}" class="hidden">
                @csrf
            </form>

            <dialog :class="open ? 'modal modal-open' : 'modal'">
                <div class="modal-box max-w-sm">
                    <h3 class="text-lg font-bold clr-primary">Confirm Logout</h3>
                    <p class="py-4 text-sm clr-primary">Are you sure you want to log out?</p>
                    <div class="modal-action">
                        <button type="button" class="btn btn-ghost clr-bg-primary text-base-100 p-2" @click="open = false">Cancel</button>
                        <button type="button" class="btn clr-bg-primary text-base-100 p-2"
                                @click="$refs.logoutForm.submit()">Logout</button>
                    </div>
                </div>
                <div class="modal-backdrop" @click="open = false"></div>
            </dialog>
        </div>
    </div>

    <div class="flex-1 flex flex-col overflow-hidden bg-gray-100 ml-16">

        <div class="clr-bg-primary shadow px-6 py-4 h-16 flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ $header ?? '' }}</h1>
            <div class="flex items-center gap-3">
                <span class="text-base-100">Hi, {{ Session::get('user')['name'] ?? Session::get('user')['Name'] ?? 'User' }}!</span>
                <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center">👤</div>
            </div>
        </div>

        <main class="flex-1 overflow-auto p-6">
            {{ $slot }}
        </main>

    </div>

</div>

</body>
</html>
