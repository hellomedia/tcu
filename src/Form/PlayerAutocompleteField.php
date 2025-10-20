<?php

namespace App\Form;

use App\Entity\Player;
use App\Repository\PlayerRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField]
class PlayerAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => 'Mon nom',
            'class' => Player::class,
            'placeholder' => '🔍',
            'choice_label' => 'name',
            // choose which fields to use in the search
            // if not passed, *all* fields are used
            'searchable_fields' => ['firstname', 'lastname'],
            'query_builder' => function (PlayerRepository $repo): QueryBuilder {
                return $repo->createQueryBuilder('p')
                    ->addOrderBy('p.lastname', 'ASC')
                ;
            },
            'multiple' => false,
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
