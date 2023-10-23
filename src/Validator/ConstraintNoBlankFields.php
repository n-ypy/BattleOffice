<?php


namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ConstraintNoBlankFields extends Constraint
{

    public string $message = 'Le formulaire "Adresse de livraison différente ?" doit être complété ou laissé en blanc.';
    public string $mode = 'strict';

    public function __construct(string $mode = null, string $message = null, array $groups = null, $payload = null)
    {
        parent::__construct([], $groups, $payload);

        $this->mode = $mode ?? $this->mode;
        $this->message = $message ?? $this->message;
    }
}