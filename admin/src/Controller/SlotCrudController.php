<?php

namespace Admin\Controller;

use App\Entity\Slot;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;

class SlotCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Slot::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Plage horaire')
            ->setEntityLabelInPlural('Plages horaires')
            ->setDefaultSort([
                'date' => 'ASC',
                'court' => 'ASC',
                'startsAt' => 'ASC',
            ])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('date', 'Jour');
        yield AssociationField::new('court', 'Terrain');

        yield TimeField::new('startsAt', 'début')
            ->setFormat('short');

        yield TimeField::new('endsAt', 'fin')
            ->setFormat('short');
    }
}
