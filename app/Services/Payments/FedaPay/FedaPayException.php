<?php

namespace App\Services\Payments\FedaPay;

use RuntimeException;

/** Erreur renvoyée par l'API FedaPay (ou configuration absente). */
class FedaPayException extends RuntimeException {}
