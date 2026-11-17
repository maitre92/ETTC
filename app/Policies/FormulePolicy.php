<?php

namespace App\Policies;

/** Permissions du manifeste config/modules/formations.php. */
class FormulePolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'formations';
    }
}
