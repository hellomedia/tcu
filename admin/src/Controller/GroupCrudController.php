<?php

namespace Admin\Controller;

use App\Entity\Group;
use App\Enum\RegistrationStatus;
use App\Repository\GroupRepository;
use App\Service\SeasonContext;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class GroupCrudController extends AbstractCrudController
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private SeasonContext $seasonContext,
        private GroupRepository $groupRepository,
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
            ->setDefaultSort(GroupRepository::ORDER)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();

        yield TextField::new('name');

        yield IntegerField::new('displayOrder', 'Ordre')
            ->setHelp('Ordre d\'affichage des poules de la saison (puis par nom)')
            ->setFormTypeOption('attr', ['min' => 0]);

        // la saison d'une poule est définie à la création (saison sélectionnée) et ne change pas
        yield AssociationField::new('season', 'Saison')
            ->hideOnForm();

        yield AssociationField::new('players', 'Nombre de joueurs')
            ->onlyOnIndex()
        ;

        // seuls les joueurs inscrits pour la saison de la poule (interfacs en hiver) : cf Group::validatePlayers()
        $season = $this->getContext()?->getEntity()?->getInstance()?->getSeason() ?? $this->seasonContext->getSelected();

        yield AssociationField::new('players', 'Joueurs')
            ->setFormTypeOption('by_reference', false)
            ->setQueryBuilder(function (QueryBuilder $qb) use ($season) {
                $qb->join('entity.registrations', 'registration')
                    ->andWhere('registration.season = :season')
                    ->andWhere('registration.status != :dismissed')
                    ->setParameter('season', $season)
                    ->setParameter('dismissed', RegistrationStatus::DISMISSED)
                    ->orderBy('entity.lastname', 'ASC')
                    ->addOrderBy('entity.firstname', 'ASC');

                if ($season?->hasInterfacs()) {
                    $qb->andWhere('registration.interfacs = true');
                }
            })
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
     * La liste des poules est la page "Poules" (cartes avec les joueurs) : GroupController::index()
     */
    public function index(AdminContext $context): RedirectResponse
    {
        return $this->redirectToRoute('admin_groups');
    }

    public function createEntity(string $entityFqcn): Group
    {
        $season = $this->seasonContext->getSelected();

        return (new Group())
            ->setSeason($season)
            ->setDisplayOrder($this->groupRepository->nextDisplayOrder($season));
    }

    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        // Keep EA behavior for "Save and continue" and "Save and add another"
        $submitButtonName = $context->getRequest()->request->all()['ea']['newForm']['btn'] ?? null;
        if ($submitButtonName !== Action::SAVE_AND_RETURN) {
            return parent::getRedirectResponseAfterSave($context, $action);
        }

        // Retour à la page d'où l'on vient (page Poules ou planning par poules), sinon page Poules
        $referrer = $context->getRequest()->query->get(EA::REFERRER);
        if (is_string($referrer) && str_starts_with($referrer, '/') && !str_starts_with($referrer, '//')) {
            return new RedirectResponse($referrer);
        }

        return new RedirectResponse($this->urlGenerator->generate('admin_groups'));
    }
}
