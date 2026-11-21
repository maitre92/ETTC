<?php

namespace App\Policies;

/** Promotions : permissions du manifeste config/modules/formations.php (comme les tarifs). */
class PromotionPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'formations';
    }
}
