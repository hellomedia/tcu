<?php

namespace Admin\Controller;

use App\Entity\Court;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CourtCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Court::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Terrain')
            ->setEntityLabelInPlural('Terrains')
            ->setDefaultSort([
                'name' => 'ASC'
            ])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom');
        yield TextField::new('description');
    }
}
