<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpenTasksController extends Controller
{
    public function __invoke(Request $request): View
    {
        $tasks = Task::query()
            ->with('trip')
            ->where('status', 'open')
            ->whereHas('trip', fn ($query) => $query->visibleTo($request->user()))
            ->get()
            ->sortBy(fn (Task $task) => sprintf(
                '%d-%012d-%d',
                $task->due_date ? 0 : 1,
                $task->due_date?->timestamp ?? PHP_INT_MAX,
                ['high' => 0, 'medium' => 1, 'low' => 2][$task->priority] ?? 3,
            ))
            ->values();

        return view('tasks.index', [
            'tasks' => $tasks,
        ]);
    }
}
