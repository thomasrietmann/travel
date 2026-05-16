@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-wide text-slate-500">Administration</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-950">Benutzer</h1>
        </div>
        <a href="{{ route('admin.mail-import.index') }}" class="inline-flex items-center justify-center rounded-md bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800">Email Import</a>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Rolle</th>
                    <th class="px-4 py-3 text-right">Reisen</th>
                    <th class="px-4 py-3 text-right">Buchungen</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($users as $user)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-950">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $user->is_admin ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-700' }}">
                                {{ $user->is_admin ? 'Admin' : 'User' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold">{{ $user->trips_count }}</td>
                        <td class="px-4 py-3 text-right font-semibold">{{ $user->bookings_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.users.show', $user) }}" class="font-medium text-slate-700 hover:text-slate-950">Details</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
