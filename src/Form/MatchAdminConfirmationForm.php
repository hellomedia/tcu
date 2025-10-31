<?php

namespace App\Form;

use App\Form\Model\MatchConfirmationInfo;
use App\Form\Type\ParticipantAdminConfirmationType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MatchAdminConfirmationForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('infos', CollectionType::class, [
            'required' => false,
            'entry_type' => ParticipantAdminConfirmationType::class,
            'entry_options' => ['label' => false],
            'allow_add' => false,
            'allow_delete' => false,
            'by_reference' => false, // ensure setters are used if needed
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MatchConfirmationInfo::class,
        ]);
    }
}