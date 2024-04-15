<?php

namespace App\Form;

use App\Entity\Adress;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Type;

class AdressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'required' => true
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'required' => true
            ])
            ->add('adress', TextType::class, [
                'label' => 'Adresse',
                'required' => true
            ])
            ->add('additionalAdress', TextType::class, [
                'label' => 'Complément adr.',
                'required' => false
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'required' => true
            ])
            ->add('zipCode', null, [
                'label' => 'Code postal',
                'required' => true,
                'constraints' => [new Type('integer'), new Positive()]
            ])
            ->add('country', CountryType::class, [
                'placeholder' => 'Sélectionnez...',
                'alpha3' => true,
                'label' => 'Pays',
                'required' => true,
                'choice_loader' => null,
                'choices' => [
                    'France' => 'FRA',
                    'Belgique' => 'BEL',
                    'Luxembourg' => 'LUX'
                ],
                'invalid_message' => "La valeur saisie n'est pas valide."
            ])
            ->add('phoneNumber', TelType::class, [
                'invalid_message' => "Le numéro saisi n'est pas valide.",
                'required' => true,
                'label' => 'Téléphone'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Adress::class,
        ]);
    }
}
