@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-md">
        <div class="mb-8">
            <h1 class="text-3xl font-semibold tracking-tight">Einloggen</h1>
            <p class="mt-2 text-sm text-slate-600">Demo: demo@tripcontrol.test / password</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="space-y-5 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-slate-700">E-Mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700">Passwort</label>
                <input id="password" name="password" type="password" required class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-slate-950 focus:ring-slate-900">
                Eingeloggt bleiben
            </label>

            <button class="w-full rounded-md bg-slate-950 px-4 py-2.5 font-medium text-white hover:bg-slate-800">Login</button>
        </form>
    </div>
@endsection
