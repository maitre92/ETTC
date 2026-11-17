<?php

namespace App\Policies;

use App\Models\Inscription;
use App\Models\User;

/** Permissions du manifeste config/modules/inscriptions.php (une par geste métier). */
class InscriptionPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'inscriptions';
    }

    public function validate(User $user, Inscription $inscription): bool
    {
        return $user->can('inscriptions.validate');
    }

    public function organize(User $user, Inscription $inscription): bool
    {
        return $user->can('inscriptions.organize');
    }

    public function cancel(User $user, Inscription $inscription): bool
    {
        return $user->can('inscriptions.cancel');
    }
}
