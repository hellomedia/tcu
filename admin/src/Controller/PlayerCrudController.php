<?php

namespace Admin\Controller;

use App\Entity\Player;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

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
        yield TextField::new('firstname', 'Prénom');
        yield TextField::new('lastname', 'Nom');

        yield ChoiceField::new('ranking', 'Classement');
        yield ChoiceField::new('gender', 'H/F');
        yield ChoiceField::new('birthyear', 'Année de naissance');
        yield ChoiceField::new('comment', 'Commentaire');

        yield BooleanField::new('interfacs', 'Interfacs');
        yield BooleanField::new('cours', 'Cours');
        yield BooleanField::new('interclubs', 'Interclubs');

        yield AssociationField::new('groups', 'Poule(s)')
            ->setFormTypeOption('by_reference', false)
        ;
        
        yield AssociationField::new('matchs', 'Matchs')
            ->hideOnForm()
        ;
    }
}
