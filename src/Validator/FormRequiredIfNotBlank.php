<?php


namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class FormRequiredIfNotBlank extends Constraint
{

}