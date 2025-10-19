<?php

namespace App\Form\Type;

use App\Entity\ParticipantConfirmationInfo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParticipantConfirmationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Display-only bits: show status, don’t let admin change history fields directly
        $builder
            // Keep participant id in form to show a label; actual entity is on the data object
            ->add('participantId', HiddenType::class, [
                'mapped' => false,
            ])
            // Optional: an unmapped read-only label in your Twig using data.participant player name

            // read-only toggles / timestamps shown in Twig
            // (don’t add fields here if they are purely display; render them from form view/data)

            // Admin action for this row:
            ->add('isConfirmedByAdmin', CheckboxType::class, [
                'mapped' => true,
                'required' => false,
                // 'data' => $options['was_confirmed_by_admin'], // set to current value, since mapped=false
                'label' => ' ',
                'row_attr' => ['class' => 'form-switch'], // for switch
                'attr'     => ['class' => 'form-check-input'], // for switch
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ParticipantConfirmationInfo::class,
            // 'was_confirmed_by_admin' => false,
        ]);

        // $resolver->setAllowedTypes('was_confirmed_by_admin', 'bool');
    }
}
