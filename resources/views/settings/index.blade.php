@extends('layouts.app')

@section('content')
    <div class="mb-8">
        <h1 class="text-3xl font-semibold tracking-tight text-slate-950">Einstellungen</h1>
        <p class="mt-2 text-slate-600">Verwalte zusätzliche Absender-Adressen für den Mail-Import.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_1.3fr]">
        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-950">Login-Adresse</h2>
            <p class="mt-2 text-sm text-slate-600">Diese Adresse bleibt für den Login zuständig und wird automatisch für den Mail-Import erkannt.</p>
            <p class="mt-4 rounded-md bg-slate-50 px-3 py-2 text-sm font-medium text-slate-800">{{ $user->email }}</p>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-950">Weitere Mailadressen</h2>
            <p class="mt-2 text-sm text-slate-600">Wenn du Mails von einer anderen persönlichen Adresse an travel@aufbollen.ch weiterleitest, füge sie hier hinzu.</p>

            <form method="POST" action="{{ route('settings.email-aliases.store') }}" class="mt-5 flex flex-col gap-3 sm:flex-row">
                @csrf
                <div class="flex-1">
                    <label for="email" class="sr-only">E-Mail-Adresse</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="name@example.com" class="w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Hinzufügen</button>
            </form>

            <div class="mt-6 divide-y divide-slate-100">
                @forelse ($user->emailAliases as $alias)
                    <div class="flex items-center justify-between gap-4 py-3">
                        <span class="text-sm font-medium text-slate-800">{{ $alias->email }}</span>
                        <form method="POST" action="{{ route('settings.email-aliases.destroy', $alias) }}">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-md border border-red-200 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50">Entfernen</button>
                        </form>
                    </div>
                @empty
                    <p class="py-4 text-sm text-slate-500">Noch keine zusätzlichen Adressen erfasst.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
