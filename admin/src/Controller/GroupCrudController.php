<?php

namespace Admin\Controller;

use App\Entity\Group;
use App\Service\SeasonContext;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
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
        private UrlGeneratorInterface $urlGenerator,
        private SeasonContext $seasonContext,
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
            ->setPageTitle(Crud::PAGE_INDEX, 'Poules - ' . $this->seasonContext->getSelected())
            ->setPageTitle(Crud::PAGE_NEW, 'Nouvelle poule - ' . $this->seasonContext->getSelected())
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

        // la saison d'une poule est définie à la création (saison sélectionnée) et ne change pas
        yield AssociationField::new('season', 'Saison')
            ->hideOnForm();

        yield AssociationField::new('players', 'Nombre de joueurs')
            ->onlyOnIndex()
        ;

        yield AssociationField::new('players', 'Joueurs')
            ->setFormTypeOption('by_reference', false)
            ->setTemplatePath('@admin/field/players.html.twig')
            ->hideOnDetail()
        ;
    
        yield AssociationField::new('players', 'Joueurs')
            ->onlyOnDetail()
            ->setTemplatePath('@admin/field/detail/players.html.twig')
        ;

        yield AssociationField::new('matchs', 'Matchs')
            ->onlyOnDetail()
            ->setTemplatePath('@admin/field/detail/matchs.html.twig')
        ;
    }

    /**
     * Uniquement les poules de la saison sélectionnée
     */
    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere('entity.season = :selectedSeason')
            ->setParameter('selectedSeason', $this->seasonContext->getSelected())
        ;
    }

    public function createEntity(string $entityFqcn): Group
    {
        return (new Group())
            ->setSeason($this->seasonContext->getSelected());
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
