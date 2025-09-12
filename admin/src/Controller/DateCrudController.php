<?php

namespace Admin\Controller;

use App\Entity\Date;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MANAGER')]
class DateCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Date::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Date')
            ->setEntityLabelInPlural('Dates')
            ->setDefaultSort([
                'date' => 'ASC'
            ])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield DateField::new('date', 'Jour')
            ->setFormat('EEEE')
            ->onlyOnIndex()
        ;

        yield DateField::new('date');
    }
}
