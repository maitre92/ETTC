<?php

namespace App\Policies;

use App\Models\Groupe;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Permissions du manifeste config/modules/seances.php. */
class GroupePolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'seances';
    }

    public function create(User $user): bool
    {
        return $user->can('seances.manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('seances.manage');
    }

    public function manageSupports(User $user, Groupe $groupe): bool
    {
        return $user->can('seances.supports');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('seances.manage');
    }
}
