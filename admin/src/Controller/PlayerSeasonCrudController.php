<?php

namespace Admin\Controller;

use Admin\Filter\RankingOrderFilter;
use Admin\Form\PlayerType;
use App\Entity\Player;
use App\Entity\PlayerSeason;
use App\Enum\RankingSource;
use App\Enum\RegistrationStatus;
use App\Service\PlayerAccountManager;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Constraints\Valid;

/**
 * Inscriptions des joueurs pour la saison sélectionnée dans l'admin :
 * classement + activités (interfacs en hiver, interclubs en été, cours)
 *
 * Une inscription pré-remplie à partir d'une saison précédente est "à confirmer" :
 * elle est confirmée ou écartée. Une inscription écartée n'apparaît plus dans la liste
 * (sauf avec le filtre Statut) et peut être rétablie.
 *
 * Le formulaire "Nouvelle inscription" a deux modes (paramètre d'URL "joueur") :
 * - joueur existant (par défaut) : autocomplete, avec un lien vers le mode nouveau joueur
 * - nouveau joueur : le joueur est créé avec son inscription (PlayerType)
 */
class PlayerSeasonCrudController extends AbstractCrudController
{
    private const CSRF_TOKEN_ID = 'registration-status';

    private const PLAYER_MODE_PARAM = 'joueur';
    private const PLAYER_MODE_NEW = 'nouveau';
    // nom tapé dans l'autocomplete, repris pour pré-remplir le nouveau joueur (new_player_link_controller.js)
    private const PLAYER_NAME_PARAM = 'nom';

    public function __construct(
        private SeasonContext $seasonContext,
        private AdminUrlGenerator $adminUrlGenerator,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private RequestStack $requestStack,
        private PlayerAccountManager $accountManager,
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
            ->setPageTitle(Crud::PAGE_NEW, 'Nouvelle inscription - ' . $season . ($this->isNewPlayerMode() ? ' (nouveau joueur)' : ''))
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

        // Inscription d'un joueur qui n'existe pas encore : le formulaire crée le joueur
        $newPlayer = Action::new('newPlayer', 'Nouveau joueur', 'fa fa-user-plus')
            ->linkToUrl(fn() => $this->generateNewPlayerUrl())
            ->createAsGlobalAction()
            ->addCssClass('btn btn-secondary');

        return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, fn(Action $action) => $action->setLabel('Inscrire un joueur'))
            ->add(Crud::PAGE_INDEX, $newPlayer)
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

        if ($pageName === Crud::PAGE_NEW && $this->isNewPlayerMode()) {
            // champ virtuel : EasyAdmin n'accepte pas un Field générique sur une association
            yield Field::new('newPlayer', 'Nouveau joueur')
                ->setFormType(PlayerType::class)
                ->setFormTypeOption('property_path', 'player')
                // valide aussi le joueur (n° d'affiliation unique...), pas seulement l'inscription
                ->setFormTypeOption('constraints', [new Valid()]);
        } else {
            $player = AssociationField::new('player', 'Joueur')
                ->autocomplete()
                // une inscription ne change pas de joueur
                ->setDisabled($pageName === Crud::PAGE_EDIT);

            if ($pageName === Crud::PAGE_NEW) {
                $player->setHelp(sprintf(
                    'Le joueur n\'existe pas ? <a href="%s" data-controller="new-player-link" data-action="new-player-link#follow">Créer un nouveau joueur</a>',
                    htmlspecialchars($this->generateNewPlayerUrl()),
                ));
            }

            yield $player;
        }

        yield ChoiceField::new('ranking', 'Classement');

        // modifier le classement à la main => source "Manuel" (PlayerSeason::setRanking())
        // enum traduisible : EasyAdmin indexe les badges sur le nom du cas, pas sa valeur
        yield ChoiceField::new('rankingSource', 'Source du classement')
            ->renderAsBadges([
                RankingSource::MANUAL->name => 'secondary',
                RankingSource::PREVIOUS_SEASON->name => 'warning',
                RankingSource::PREVISIONAL->name => 'warning',
                RankingSource::OFFICIAL->name => 'success',
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
                RegistrationStatus::PENDING->name => 'warning',
                RegistrationStatus::CONFIRMED->name => 'success',
                RegistrationStatus::DISMISSED->name => 'secondary',
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
        $registration = (new PlayerSeason())
            ->setSeason($this->seasonContext->getSelected());

        if ($this->isNewPlayerMode()) {
            $player = (new Player())
                ->setLastname($this->requestStack->getCurrentRequest()?->query->get(self::PLAYER_NAME_PARAM));
            $player->addSeason($registration);
        }

        return $registration;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        // nouveau joueur avec un email : son compte est créé aussi
        $this->accountManager->sync($entityInstance->getPlayer());
        parent::persistEntity($entityManager, $entityInstance);
    }

    /**
     * Comme le parent, sans les paramètres du mode nouveau joueur : le nom tapé dans l'autocomplete
     * ne concerne que ce formulaire, et le mode ne se garde que pour "Créer et ajouter un autre"
     */
    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $submitButtonName = $context->getRequest()->request->all()['ea']['newForm']['btn'] ?? null;

        $url = $this->adminUrlGenerator->unset(self::PLAYER_NAME_PARAM);
        if ($submitButtonName !== Action::SAVE_AND_ADD_ANOTHER) {
            $url->unset(self::PLAYER_MODE_PARAM);
        }

        $url = match ($submitButtonName) {
            Action::SAVE_AND_CONTINUE => $url->setAction(Action::EDIT)->setEntityId($context->getEntity()->getPrimaryKeyValue())->generateUrl(),
            Action::SAVE_AND_RETURN => $url->setAction(Action::INDEX)->generateUrl(),
            Action::SAVE_AND_ADD_ANOTHER => $url->setAction(Action::NEW)->generateUrl(),
            default => $this->generateUrl($context->getDashboardRouteName()),
        };

        return $this->redirect($url);
    }

    private function isNewPlayerMode(): bool
    {
        return $this->requestStack->getCurrentRequest()?->query->get(self::PLAYER_MODE_PARAM) === self::PLAYER_MODE_NEW;
    }

    /**
     * Formulaire "Nouvelle inscription" en mode nouveau joueur
     */
    private function generateNewPlayerUrl(): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController(self::class)
            ->setAction(Action::NEW)
            ->set(self::PLAYER_MODE_PARAM, self::PLAYER_MODE_NEW)
            ->generateUrl();
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

        $this->addFlash('success', sprintf('%s : inscription %s.', $registration->getPlayer()->getName(), self::statusVerb($status)));

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
        $count = 0;

        foreach ($batchActionDto->getEntityIds() as $id) {
            $registration = $entityManager->find(PlayerSeason::class, $id);

            // on n'écarte que des inscriptions à confirmer : une inscription confirmée se supprime
            if ($registration === null || ($status === RegistrationStatus::DISMISSED && !$registration->isPending())) {
                continue;
            }

            $registration->setStatus($status);
            $count++;
        }

        $entityManager->flush();

        $this->addFlash($count ? 'success' : 'warning', match ($count) {
            0 => 'Aucune inscription modifiée.',
            1 => sprintf('1 inscription %s.', self::statusVerb($status)),
            default => sprintf('%d inscriptions %s.', $count, self::statusVerb($status, plural: true)),
        });

        return $this->redirect($batchActionDto->getReferrerUrl());
    }

    private static function statusVerb(RegistrationStatus $status, bool $plural = false): string
    {
        return match ($status) {
            RegistrationStatus::CONFIRMED => 'confirmée',
            RegistrationStatus::DISMISSED => 'écartée',
            RegistrationStatus::PENDING => 'rétablie',
        } . ($plural ? 's' : '');
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
