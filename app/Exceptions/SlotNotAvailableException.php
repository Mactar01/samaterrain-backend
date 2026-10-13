<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception levée quand un créneau est déjà réservé (conflit 409).
 */
class SlotNotAvailableException extends Exception
{
    public function __construct(string $message = 'Ce créneau n\'est plus disponible.')
    {
        parent::__construct($message);
    }
}
