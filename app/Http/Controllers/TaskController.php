<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskRequest;
use App\Models\Task;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function create(Trip $trip): View
    {
        $this->authorize('update', $trip);

        return view('tasks.create', ['trip' => $trip, 'task' => new Task()]);
    }

    public function store(TaskRequest $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        $trip->tasks()->create($request->validated());

        return redirect()->route('trips.show', $trip)->with('status', 'Aufgabe wurde erstellt.');
    }

    public function edit(Task $task): View
    {
        $this->authorize('update', $task);

        return view('tasks.edit', ['trip' => $task->trip, 'task' => $task]);
    }

    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $task->update($request->validated());

        return redirect()->route('trips.show', $task->trip)->with('status', 'Aufgabe wurde aktualisiert.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $trip = $task->trip;
        $task->delete();

        return redirect()->route('trips.show', $trip)->with('status', 'Aufgabe wurde geloescht.');
    }
}
