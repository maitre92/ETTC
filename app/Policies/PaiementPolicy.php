<?php

namespace App\Policies;

use App\Models\Paiement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Permissions du manifeste config/modules/paiements.php. Aucun paiement ne se supprime (suivi financier). */
class PaiementPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'paiements';
    }

    public function create(User $user): bool
    {
        return $user->can('paiements.record');
    }

    public function verify(User $user, Paiement $paiement): bool
    {
        return $user->can('paiements.verify');
    }

    public function refund(User $user, Paiement $paiement): bool
    {
        return $user->can('paiements.refund');
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
