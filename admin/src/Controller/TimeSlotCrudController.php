<?php

namespace Admin\Controller;

use App\Entity\TimeSlot;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

class TimeSlotCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TimeSlot::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Plage horaire')
            ->setEntityLabelInPlural('Plages horaires')
            ->setDefaultSort([
                'date' => 'ASC',
                'time' => 'ASC',
            ])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();

        yield AssociationField::new('date', 'Jour');

        yield AssociationField::new('time', 'Heure');

        yield AssociationField::new('endTime', 'Heure de fin');

        yield AssociationField::new('match', 'Match');
    }
}
