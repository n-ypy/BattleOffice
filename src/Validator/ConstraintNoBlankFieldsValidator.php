<?php


namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ConstraintNoBlankFieldsValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint)
    {
        if (!$constraint instanceof ConstraintNoBlankFields) {
            throw new UnexpectedTypeException($constraint, ConstraintNoBlankFields::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if($value->getFirstName() === null 
        || $value->getLastName() === null 
        || $value->getAdress() === null 
        || $value->getCity() === null 
        || $value->getZipCode() === null 
        || $value->getCountry() === null 
        || $value->getPhoneNumber() === null){
            $this->context->buildViolation($constraint->message)
            ->addViolation();
        }
    }
}