<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'TripControl') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('brand/tripcontrol-icon.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('brand/apple-touch-icon.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('brand/apple-touch-icon-180x180.png') }}">
    <link rel="apple-touch-icon" sizes="167x167" href="{{ asset('brand/apple-touch-icon-167x167.png') }}">
    <link rel="apple-touch-icon" sizes="152x152" href="{{ asset('brand/apple-touch-icon-152x152.png') }}">
    <link rel="apple-touch-icon" sizes="120x120" href="{{ asset('brand/apple-touch-icon-120x120.png') }}">
    @stack('head')
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        @yield('content')
    </main>
</body>
</html>
