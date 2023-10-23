<?php


namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ConstraintApprrouvedPaymentMethodsValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint)
    {
        if (!$constraint instanceof ConstraintApprrouvedPaymentMethods) {
            throw new UnexpectedTypeException($constraint, ConstraintApprrouvedPaymentMethods::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if($value !== "paypal" && $value !== "stripe"){
            $this->context->buildViolation($constraint->message)
            ->addViolation();
        }
    }
}