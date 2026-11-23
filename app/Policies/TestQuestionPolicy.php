<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Banque de questions du test en ligne : réglage du test (tests.configure). */
class TestQuestionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tests.configure');
    }

    public function create(User $user): bool
    {
        return $user->can('tests.configure');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('tests.configure');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('tests.configure');
    }
}
