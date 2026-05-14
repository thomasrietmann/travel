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

            <nav class="flex items-center gap-3 text-sm">
                @auth
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
    </div>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
