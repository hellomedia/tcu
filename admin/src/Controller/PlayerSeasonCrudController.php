<?php

namespace Admin\Controller;

use Admin\Filter\RankingOrderFilter;
use App\Entity\PlayerSeason;
use App\Enum\RankingSource;
use App\Enum\RegistrationStatus;
use App\Service\SeasonContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Inscriptions des joueurs pour la saison sélectionnée dans l'admin :
 * classement + activités (interfacs en hiver, interclubs en été, cours)
 *
 * Une inscription pré-remplie à partir d'une saison précédente est "à confirmer" :
 * elle est confirmée ou écartée. Une inscription écartée n'apparaît plus dans la liste
 * (sauf avec le filtre Statut) et peut être rétablie.
 */
class PlayerSeasonCrudController extends AbstractCrudController
{
    private const CSRF_TOKEN_ID = 'registration-status';

    public function __construct(
        private SeasonContext $seasonContext,
        private AdminUrlGenerator $adminUrlGenerator,
        private CsrfTokenManagerInterface $csrfTokenManager,
    )
    {
    }

    public static function getEntityFqcn(): string
    {
        return PlayerSeason::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        $season = $this->seasonContext->getSelected();

        return $crud
            ->setEntityLabelInSingular('Inscription')
            ->setEntityLabelInPlural('Inscriptions')
            ->setPageTitle(Crud::PAGE_INDEX, 'Inscriptions - ' . $season)
            ->setPageTitle(Crud::PAGE_NEW, 'Nouvelle inscription - ' . $season)
            ->setSearchFields(['player.firstname', 'player.lastname'])
            ->setDefaultSort([
                'player.lastname' => 'ASC',
            ])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $confirm = Action::new('confirm', 'Confirmer', 'fa fa-check')
            ->linkToUrl(fn(PlayerSeason $registration) => $this->generateStatusUrl($registration, RegistrationStatus::CONFIRMED))
            ->displayIf(fn(PlayerSeason $registration) => $registration->isPending());

        $dismiss = Action::new('dismiss', 'Écarter', 'fa fa-xmark')
            ->linkToUrl(fn(PlayerSeason $registration) => $this->generateStatusUrl($registration, RegistrationStatus::DISMISSED))
            ->displayIf(fn(PlayerSeason $registration) => $registration->isPending());

        $restore = Action::new('restore', 'Rétablir', 'fa fa-rotate-left')
            ->linkToUrl(fn(PlayerSeason $registration) => $this->generateStatusUrl($registration, RegistrationStatus::PENDING))
            ->displayIf(fn(PlayerSeason $registration) => $registration->isDismissed());

        $confirmBatch = Action::new('confirmBatch', 'Confirmer')
            ->linkToCrudAction('confirmBatch')
            ->addCssClass('btn btn-primary')
            ->setIcon('fa fa-check');

        $dismissBatch = Action::new('dismissBatch', 'Écarter')
            ->linkToCrudAction('dismissBatch')
            ->addCssClass('btn btn-secondary')
            ->setIcon('fa fa-xmark');

        // Classements récupérés sur mon-classement-tennis.be : Admin\Controller\RankingController
        $previsionalRankings = Action::new('previsionalRankings', 'Classements prévisionnels', 'fa fa-cloud-arrow-down')
            ->linkToRoute('admin_rankings', ['mode' => 'previsionnel'])
            ->createAsGlobalAction()
            ->addCssClass('btn btn-secondary');

        $officialRankings = Action::new('officialRankings', 'Classements officiels', 'fa fa-cloud-arrow-down')
            ->linkToRoute('admin_rankings', ['mode' => 'officiel'])
            ->createAsGlobalAction()
            ->addCssClass('btn btn-secondary');

        return $actions
            ->add(Crud::PAGE_INDEX, $previsionalRankings)
            ->add(Crud::PAGE_INDEX, $officialRankings)
            ->add(Crud::PAGE_INDEX, $confirm)
            ->add(Crud::PAGE_INDEX, $dismiss)
            ->add(Crud::PAGE_INDEX, $restore)
            ->reorder(Crud::PAGE_INDEX, ['confirm', 'dismiss', 'restore', Action::DETAIL, Action::EDIT, Action::DELETE])
            ->addBatchAction($confirmBatch)
            ->addBatchAction($dismissBatch)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        $season = $this->seasonContext->getSelected();

        yield AssociationField::new('player', 'Joueur')
            ->autocomplete()
            // une inscription ne change pas de joueur
            ->setDisabled($pageName === Crud::PAGE_EDIT);

        yield ChoiceField::new('ranking', 'Classement');

        // modifier le classement à la main => source "Manuel" (PlayerSeason::setRanking())
        yield ChoiceField::new('rankingSource', 'Source du classement')
            ->renderAsBadges([
                RankingSource::MANUAL->value => 'secondary',
                RankingSource::PREVIOUS_SEASON->value => 'warning',
                RankingSource::PREVISIONAL->value => 'warning',
                RankingSource::OFFICIAL->value => 'success',
            ])
            ->hideOnForm();

        yield DateTimeField::new('rankingFetchedAt', 'Classement récupéré le')
            ->onlyOnDetail();

        // interfacs en hiver, interclubs en été
        if ($season?->hasInterfacs()) {
            yield BooleanField::new('interfacs', 'Interfacs')
                ->renderAsSwitch(true);
        }

        yield BooleanField::new('cours', 'Cours')
            ->renderAsSwitch(true);

        if ($season?->hasInterclubs()) {
            yield BooleanField::new('interclubs', 'Interclubs')
                ->renderAsSwitch(true);
        }

        yield ChoiceField::new('status', 'Statut')
            ->renderAsBadges([
                RegistrationStatus::PENDING->value => 'warning',
                RegistrationStatus::CONFIRMED->value => 'success',
                RegistrationStatus::DISMISSED->value => 'secondary',
            ])
            ->setHelp('Une inscription pré-remplie à partir d\'une saison précédente doit être vérifiée (classement, activités) puis confirmée, ou écartée si le joueur ne s\'inscrit pas.');
    }

    public function configureFilters(Filters $filters): Filters
    {
        $season = $this->seasonContext->getSelected();

        $filters->add(ChoiceFilter::new('status', 'Statut')
            ->setChoices(RegistrationStatus::getFilterChoices())
        );

        if ($season?->hasInterfacs()) {
            $filters->add(BooleanFilter::new('interfacs'));
        }

        if ($season?->hasInterclubs()) {
            $filters->add(BooleanFilter::new('interclubs'));
        }

        return $filters
            ->add(BooleanFilter::new('cours'))
            // custom filter
            ->add(RankingOrderFilter::new(label: 'Classement'))
            ->add(ChoiceFilter::new('rankingSource', 'Source du classement')
                ->setChoices(RankingSource::getFilterChoices())
            )
        ;
    }

    /**
     * Uniquement les inscriptions de la saison sélectionnée.
     * Sans filtre sur le statut, les inscriptions écartées sont masquées.
     */
    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere('entity.season = :selectedSeason')
            ->setParameter('selectedSeason', $this->seasonContext->getSelected())
        ;

        if (!isset($searchDto->getAppliedFilters()['status'])) {
            $qb->andWhere('entity.status != :dismissed')
                ->setParameter('dismissed', RegistrationStatus::DISMISSED);
        }

        return $qb;
    }

    public function createEntity(string $entityFqcn): PlayerSeason
    {
        return (new PlayerSeason())
            ->setSeason($this->seasonContext->getSelected());
    }

    /**
     * Confirmer / écarter / rétablir une inscription depuis la liste
     */
    public function changeStatus(AdminContext $context, EntityManagerInterface $entityManager): Response
    {
        $request = $context->getRequest();

        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->query->get('token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $status = RegistrationStatus::tryFrom((string) $request->query->get('status'));
        $registration = $context->getEntity()->getInstance();

        if ($status === null || !$registration instanceof PlayerSeason) {
            throw $this->createNotFoundException();
        }

        $registration->setStatus($status);
        $entityManager->flush();

        return $this->redirect($this->getIndexUrl());
    }

    public function confirmBatch(BatchActionDto $batchActionDto, EntityManagerInterface $entityManager): Response
    {
        return $this->changeStatusBatch($batchActionDto, $entityManager, RegistrationStatus::CONFIRMED);
    }

    public function dismissBatch(BatchActionDto $batchActionDto, EntityManagerInterface $entityManager): Response
    {
        return $this->changeStatusBatch($batchActionDto, $entityManager, RegistrationStatus::DISMISSED);
    }

    private function changeStatusBatch(BatchActionDto $batchActionDto, EntityManagerInterface $entityManager, RegistrationStatus $status): Response
    {
        foreach ($batchActionDto->getEntityIds() as $id) {
            $registration = $entityManager->find(PlayerSeason::class, $id);

            // on n'écarte que des inscriptions à confirmer : une inscription confirmée se supprime
            if ($registration === null || ($status === RegistrationStatus::DISMISSED && !$registration->isPending())) {
                continue;
            }

            $registration->setStatus($status);
        }

        $entityManager->flush();

        return $this->redirect($batchActionDto->getReferrerUrl());
    }

    private function generateStatusUrl(PlayerSeason $registration, RegistrationStatus $status): string
    {
        // on garde les filtres, le tri et la page de la liste
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction('changeStatus')
            ->setEntityId($registration->getId())
            ->set('status', $status->value)
            ->set('token', $this->csrfTokenManager->getToken(self::CSRF_TOKEN_ID)->getValue())
            ->generateUrl();
    }

    /**
     * Retour à la liste, avec ses filtres, son tri et sa page
     */
    private function getIndexUrl(): string
    {
        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->unset('entityId')
            ->unset('status')
            ->unset('token');

        return $url->generateUrl();
    }
}
