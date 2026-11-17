<?php

namespace App\Policies;

/** Permissions du manifeste config/modules/formations.php. */
class NiveauPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'formations';
    }
}
