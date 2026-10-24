@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-wide text-slate-500">Administration</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-950">Email Import</h1>
            <p class="mt-2 text-sm text-slate-600">{{ $logPath }}</p>
        </div>
        <form method="POST" action="{{ route('admin.mail-import.store') }}">
            @csrf
            <button class="inline-flex items-center justify-center rounded-md bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800">Import manuell starten</button>
        </form>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between gap-4">
            <h2 class="text-lg font-semibold text-slate-950">Logfile</h2>
            <span class="text-sm text-slate-500">Letzte 50 Zeilen</span>
        </div>

        @if (empty($logLines))
            <div class="rounded-md border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">Noch keine Logeintraege vorhanden.</div>
        @else
            <pre class="max-h-[32rem] overflow-auto rounded-md bg-slate-950 p-4 text-xs leading-relaxed text-slate-100">{{ implode("\n", $logLines) }}</pre>
        @endif
    </div>
    <section class="mt-6 rounded-lg border bg-white p-6">
        <h2 class="font-semibold">Letzte Importversuche</h2>
        <p class="mt-2 text-sm">Blockierte oder fehlgeschlagene Mails bleiben zur Klärung im Postfach. Nach der Korrektur kann ein Administrator den Import anhand der ID erneut freigeben.</p>
        <table class="mt-4 w-full text-left text-sm">
            <thead><tr><th>ID</th><th>Status</th><th>Versuche</th><th>Aktualisiert</th></tr></thead>
            <tbody>@foreach ($imports as $import)<tr><td>{{ $import->id }}</td><td>{{ $import->status }}</td><td>{{ $import->attempts }}</td><td>{{ $import->updated_at }}</td></tr>@endforeach</tbody>
        </table>
    </section>
@endsection
