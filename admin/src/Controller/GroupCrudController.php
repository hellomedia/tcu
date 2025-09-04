<?php

namespace Admin\Controller;

use App\Entity\Group;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class GroupCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Group::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Poule')
            ->setEntityLabelInPlural('Poules')
            ->setDefaultSort([
                'name' => 'ASC'
            ])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();

        yield TextField::new('name');

        yield AssociationField::new('players', 'Nombre de joueurs')
            ->onlyOnIndex()
        ;

        yield AssociationField::new('players', 'Joueurs')
            ->setTemplatePath('@admin/player/list.html.twig')
        ;
    }
}
