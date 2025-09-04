<?php

namespace Admin\Controller;

use App\Entity\Time;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;

class TimeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Time::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Heure')
            ->setEntityLabelInPlural('Heures')
            ->setDefaultSort([
                'time' => 'ASC'
            ])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();

        yield TimeField::new('time')
            ->setFormat('short')
        ;

    }
}
