<?php

namespace App\Form;

use App\Entity\Order;
use App\Entity\Product;
use App\Validator\ConstraintApprrouvedPaymentMethods;
use App\Validator\ConstraintNoBlankFields;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('product', EntityType::class, [
                'class' => Product::class,
                'label' => false,
                'multiple' => false,
                'expanded' => true,
                'required' => true
            ])
            ->add('customer', CustomerType::class)
            ->add('billingAdress', AdressType::class)
            ->add('shippingAdress', AdressType::class, [
                'required' => false,
                'constraints' => [new ConstraintNoBlankFields()]
            ])
            ->add(
                'paymentMethod',
                options: [
                    'required' => true,
                    'constraints' => [new ConstraintApprrouvedPaymentMethods()]
                ]
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
        ]);
    }
}
