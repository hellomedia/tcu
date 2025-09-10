<?php

namespace Admin\Controller;

use App\Entity\Slot;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
class SlotCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Slot::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Créneau horaire')
            ->setEntityLabelInPlural('Créneaux horaires')
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

    public function configureActions(Actions $actions): Actions
    {
        $bulkAdd = Action::new('bulkAdd', 'Ajouter des créneaux')
            ->linkToRoute('admin_planning_slot_bulk_add')
            ->setIcon('fa fa-add')
            ->addCssClass('btn-primary')
            ->createAsGlobalAction();

        $actions
            ->add(Action::INDEX, $bulkAdd);

        return $actions;
    }
}
