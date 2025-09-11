<?php

namespace Admin\Controller;

use App\Entity\InterfacMatch;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
class InterfacMatchCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return InterfacMatch::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Match')
            ->setEntityLabelInPlural('Matchs')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();

        yield AssociationField::new('booking', 'Booking');
        
        yield AssociationField::new('players', 'Joueurs')
            ->setFormTypeOption('by_reference', false)
            ->setTemplatePath('@admin/player/list.html.twig')
        ;

        yield AssociationField::new('group');
    }
}
