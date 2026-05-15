<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'TripControl') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <div class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('dashboard') }}" class="text-xl font-semibold tracking-tight text-slate-950">TripControl</a>

            <button type="button" data-mobile-menu-button class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-slate-300 text-slate-700 hover:bg-slate-100 md:hidden" aria-label="Menü öffnen" aria-expanded="false">
                <span class="sr-only">Menü öffnen</span>
                <span class="block h-0.5 w-5 rounded bg-current"></span>
                <span class="absolute block h-0.5 w-5 translate-y-1.5 rounded bg-current"></span>
                <span class="absolute block h-0.5 w-5 -translate-y-1.5 rounded bg-current"></span>
            </button>

            <nav class="hidden items-center justify-end gap-2 text-sm md:flex">
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-md px-3 py-2 font-medium {{ request()->routeIs('dashboard') ? 'bg-slate-100 text-slate-950' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">Übersicht</a>
                    <a href="{{ route('tasks.index') }}" class="rounded-md px-3 py-2 font-medium {{ request()->routeIs('tasks.index') ? 'bg-slate-100 text-slate-950' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">Tasks</a>
                    <a href="{{ route('documents.index') }}" class="rounded-md px-3 py-2 font-medium {{ request()->routeIs('documents.index') ? 'bg-slate-100 text-slate-950' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">Dokumente</a>
                    <a href="{{ route('countdown.index') }}" class="rounded-md px-3 py-2 font-medium {{ request()->routeIs('countdown.index') ? 'bg-slate-100 text-slate-950' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">Countdown</a>
                    <a href="{{ route('settings.index') }}" class="rounded-md px-3 py-2 font-medium {{ request()->routeIs('settings.*') ? 'bg-slate-100 text-slate-950' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">Einstellungen</a>
                    <a href="{{ route('trips.create') }}" class="rounded-md bg-slate-950 px-3 py-2 font-medium text-white hover:bg-slate-800">Neue Reise</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded-md border border-slate-300 px-3 py-2 font-medium text-slate-700 hover:bg-slate-100">Logout</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="font-medium text-slate-700 hover:text-slate-950">Login</a>
                    <a href="{{ route('register') }}" class="rounded-md bg-slate-950 px-3 py-2 font-medium text-white hover:bg-slate-800">Registrieren</a>
                @endauth
            </nav>
        </div>

        <nav data-mobile-menu class="hidden border-t border-slate-200 px-4 py-3 text-sm md:hidden">
            <div class="flex flex-col gap-1">
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-md px-3 py-2 font-medium {{ request()->routeIs('dashboard') ? 'bg-slate-100 text-slate-950' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">Übersicht</a>
                    <a href="{{ route('tasks.index') }}" class="rounded-md px-3 py-2 font-medium {{ request()->routeIs('tasks.index') ? 'bg-slate-100 text-slate-950' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">Tasks</a>
                    <a href="{{ route('documents.index') }}" class="rounded-md px-3 py-2 font-medium {{ request()->routeIs('documents.index') ? 'bg-slate-100 text-slate-950' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">Dokumente</a>
                    <a href="{{ route('countdown.index') }}" class="rounded-md px-3 py-2 font-medium {{ request()->routeIs('countdown.index') ? 'bg-slate-100 text-slate-950' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">Countdown</a>
                    <a href="{{ route('settings.index') }}" class="rounded-md px-3 py-2 font-medium {{ request()->routeIs('settings.*') ? 'bg-slate-100 text-slate-950' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}">Einstellungen</a>
                    <a href="{{ route('trips.create') }}" class="rounded-md bg-slate-950 px-3 py-2 font-medium text-white hover:bg-slate-800">Neue Reise</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="w-full rounded-md border border-slate-300 px-3 py-2 text-left font-medium text-slate-700 hover:bg-slate-100">Logout</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="rounded-md px-3 py-2 font-medium text-slate-700 hover:bg-slate-100 hover:text-slate-950">Login</a>
                    <a href="{{ route('register') }}" class="rounded-md bg-slate-950 px-3 py-2 font-medium text-white hover:bg-slate-800">Registrieren</a>
                @endauth
            </div>
        </nav>
    </div>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>
    <script>
        document.querySelectorAll('[data-mobile-menu-button]').forEach((button) => {
            const menu = document.querySelector('[data-mobile-menu]');

            button.addEventListener('click', () => {
                const isOpen = !menu.classList.contains('hidden');

                menu.classList.toggle('hidden', isOpen);
                button.setAttribute('aria-expanded', String(!isOpen));
            });
        });
    </script>
</body>
</html>
