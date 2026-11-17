<?php

namespace App\Policies;

use App\Models\TestNiveau;
use App\Models\User;

/** Permissions du manifeste config/modules/tests.php (une par geste métier). */
class TestNiveauPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'tests';
    }

    public function schedule(User $user, TestNiveau $test): bool
    {
        return $user->can('tests.schedule');
    }

    public function payment(User $user, TestNiveau $test): bool
    {
        return $user->can('tests.payment');
    }

    public function result(User $user, TestNiveau $test): bool
    {
        return $user->can('tests.result');
    }
}
