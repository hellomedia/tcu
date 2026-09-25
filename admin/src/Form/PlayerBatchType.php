<?php

namespace Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Page "Compléter les joueurs" (PlayerBatchController) : une ligne par joueur, indexée par id
 *
 * Les données sont un tableau ['players' => [id => Player]].
 */
class PlayerBatchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('players', CollectionType::class, [
            'entry_type' => PlayerBatchRowType::class,
            'label' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
