<?php

namespace Admin\Controller;

use App\Entity\Player;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;

class PlayerCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Player::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Joueur')
            ->setEntityLabelInPlural('Joueurs')
            ->setDefaultSort([
                'lastname' => 'ASC',
            ])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('lastname', 'Nom');
        yield TextField::new('firstname', 'Prénom');

        yield AssociationField::new('user')->setPermission('ROLE_SUPER_ADMIN');

        yield ChoiceField::new('ranking', 'Classement');
        yield ChoiceField::new('gender', 'H/F');
        yield ChoiceField::new('birthyear', 'Année de naissance');

        yield BooleanField::new('interfacs', 'Interfacs')
            ->renderAsSwitch(true);

        yield AssociationField::new('groups', 'Poule(s)')
            ->setTemplatePath('@admin/field/groups.html.twig')
            ->setFormTypeOption('by_reference', false);

        yield BooleanField::new('cours', 'Cours')
            ->renderAsSwitch(true);
        yield BooleanField::new('interclubs', 'Interclubs')
            ->renderAsSwitch(true);

        yield TextField::new('phone', 'Téléphone');

        yield TextareaField::new('availabilities', 'Dispos');
        yield TextareaField::new('comment', 'Commentaire');

    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('interfacs'))
            ->add(BooleanFilter::new('interclubs'))
            ->add(BooleanFilter::new('cours'))
        ;
    }

}
