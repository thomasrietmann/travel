<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function update(User $user, Task $task): bool
    {
        return $task->trip->isAccessibleBy($user);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }
}
