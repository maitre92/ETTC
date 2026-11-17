<?php

namespace App\Services\Paiements\Gateway;

use RuntimeException;

/** Le prestataire est injoignable ou a refusé la demande : rien n'est confirmé, l'apprenant peut réessayer. */
class GatewayException extends RuntimeException {}
