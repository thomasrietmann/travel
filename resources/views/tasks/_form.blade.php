@php
    use App\Models\Task;
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <div class="md:col-span-2">
        <label for="title" class="block text-sm font-medium text-slate-700">Titel</label>
        <input id="title" name="title" value="{{ old('title', $task->title) }}" required class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
        @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="due_date" class="block text-sm font-medium text-slate-700">Faellig am</label>
        <input id="due_date" name="due_date" type="date" value="{{ old('due_date', $task->due_date?->format('Y-m-d')) }}" class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
        @error('due_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="priority" class="block text-sm font-medium text-slate-700">Prioritaet</label>
        <select id="priority" name="priority" required class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
            @foreach (Task::PRIORITIES as $priority)
                <option value="{{ $priority }}" @selected(old('priority', $task->priority ?? 'medium') === $priority)>{{ Task::PRIORITY_LABELS[$priority] }}</option>
            @endforeach
        </select>
        @error('priority') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
        <select id="status" name="status" required class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
            @foreach (Task::STATUSES as $status)
                <option value="{{ $status }}" @selected(old('status', $task->status ?? 'open') === $status)>{{ Task::STATUS_LABELS[$status] }}</option>
            @endforeach
        </select>
        @error('status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label for="notes" class="block text-sm font-medium text-slate-700">Notizen</label>
        <textarea id="notes" name="notes" rows="4" class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">{{ old('notes', $task->notes) }}</textarea>
        @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
</div>
