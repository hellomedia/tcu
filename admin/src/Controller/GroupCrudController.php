<?php

namespace Admin\Controller;

use App\Entity\Group;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class GroupCrudController extends AbstractCrudController
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator
    )
    {

    }

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
            ->setFormTypeOption('by_reference', false)
            ->setTemplatePath('@admin/player/list.html.twig')
        ;
    }

    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        // Keep EA behavior for "Save and continue" on edit
        if ($action === Action::SAVE_AND_CONTINUE) {
            return parent::getRedirectResponseAfterSave($context, $action);
        }

        // Only change redirect when coming from the EDIT page
        if ($context->getCrud()->getCurrentPage() === Crud::PAGE_EDIT) {
            $entity = $context->getEntity()->getInstance();

            $url = $this->urlGenerator->generate('admin_planning_groups');

            return new RedirectResponse($url);
        }

        // For other pages (e.g. NEW), keep default
        return parent::getRedirectResponseAfterSave($context, $action);
    }
}
