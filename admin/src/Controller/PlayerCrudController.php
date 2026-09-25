<?php

namespace Admin\Controller;

use Admin\Mailer\InvitationMailer;
use App\Entity\Player;
use App\Enum\Gender;
use App\Repository\UserRepository;
use App\Service\PlayerAccountManager;
use App\Service\SeasonContext;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class PlayerCrudController extends AbstractCrudController
{
    public function __construct(
        private SeasonContext $seasonContext,
        private PlayerAccountManager $accountManager,
        private UserRepository $userRepository,
        private UrlGeneratorInterface $urlGenerator,
        private CsrfTokenManagerInterface $csrfTokenManager,
    )
    {
    }

    public static function getEntityFqcn(): string
    {
        return Player::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Joueur')
            ->setEntityLabelInPlural('Joueurs')
            ->overrideTemplate('crud/index', '@admin/player/index.html.twig')
            ->setDefaultSort([
                'lastname' => 'ASC',
            ])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        // nom, téléphone, email, n° d'affiliation de tous les joueurs incomplets en une page : Admin\Controller\PlayerBatchController
        $batch = Action::new('batch', 'Compléter les joueurs', 'fa fa-list-check')
            ->linkToRoute('admin_players_batch')
            ->createAsGlobalAction()
            ->addCssClass('btn btn-secondary');

        // Invitations des comptes créés par un admin : Admin\Controller\InvitationController
        $invite = Action::new('invite', 'Inviter', 'fa fa-envelope')
            ->linkToUrl(fn(Player $player) => $this->generateInvitationUrl('admin_player_invite', ['id' => $player->getId()]))
            ->displayIf(fn(Player $player) => $player->getUser()?->mustChoosePassword() ?? false);

        $toInvite = count($this->userRepository->findPlayersToInvite());
        // ouvre la modale de confirmation (@admin/player/_invite_modal.html.twig), qui contient le lien d'envoi
        $invitePending = Action::new('invitePending', sprintf('Inviter les nouveaux comptes (%d)', $toInvite), 'fa fa-envelope')
            ->linkToUrl('#')
            ->setHtmlAttributes(['data-bs-toggle' => 'modal', 'data-bs-target' => '#modal-invite'])
            ->createAsGlobalAction()
            ->addCssClass('btn btn-secondary' . ($toInvite ? '' : ' disabled'));

        return $actions
            ->add(Crud::PAGE_INDEX, $batch)
            ->add(Crud::PAGE_INDEX, $invitePending)
            ->add(Crud::PAGE_INDEX, $invite)
            ->reorder(Crud::PAGE_INDEX, ['invite', Action::DETAIL, Action::EDIT, Action::DELETE]);
    }

    // modale de confirmation des invitations sur la liste (@admin/player/index.html.twig)
    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        if ($responseParameters->get('pageName') === Crud::PAGE_INDEX) {
            $responseParameters->set('to_invite', $this->userRepository->findPlayersToInvite());
            $responseParameters->set('invite_url', $this->generateInvitationUrl('admin_players_invite_pending'));
            $responseParameters->set('lifetime_days', InvitationMailer::LIFETIME_DAYS);
        }

        return $responseParameters;
    }

    private function generateInvitationUrl(string $route, array $parameters = []): string
    {
        return $this->urlGenerator->generate($route, $parameters + [
            'token' => $this->csrfTokenManager->getToken(InvitationController::CSRF_TOKEN_ID)->getValue(),
        ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('lastname', 'Nom');
        yield TextField::new('firstname', 'Prénom');

        yield TextField::new('affiliationNumber', 'N° d\'affiliation')
            ->setHelp('Numéro d\'affiliation à la fédération. Permet de récupérer le classement du joueur.');

        // email du compte lié : un email sur un joueur sans compte crée le compte (PlayerAccountManager)
        yield EmailField::new('accountEmail', 'Email')
            // sur la liste : colonne Compte
            ->hideOnIndex()
            ->setHelp('Email du compte du joueur. Pour un joueur sans compte, un compte est créé avec cet email : la personne est ensuite invitée à choisir son mot de passe (« Inviter » dans la liste).');

        // compte : invitation, mot de passe choisi, dernière connexion
        yield TextField::new('accountStatus', 'Compte')
            ->setTemplatePath('@admin/field/account.html.twig')
            ->setSortable(false)
            ->hideOnForm();

        yield AssociationField::new('user')->setPermission('ROLE_SUPER_ADMIN');

        yield ChoiceField::new('gender', 'H/F');
        yield ChoiceField::new('birthyear', 'Année de naissance');

        // Le classement et les activités (interfacs, cours, interclubs) changent à chaque saison :
        // ils se gèrent dans les inscriptions (PlayerSeasonCrudController)
        $season = $this->seasonContext->getSelected();

        yield AssociationField::new('seasons', 'Inscription ' . $season)
            ->setTemplatePath('@admin/field/registration.html.twig')
            ->setCustomOption('season', $season)
            ->setSortable(false)
            ->hideOnForm();

        yield AssociationField::new('groups', 'Poule(s)')
            ->setTemplatePath('@admin/field/groups.html.twig')
            ->setCustomOption('season', $season)
            ->hideOnForm();

        yield TextField::new('phone', 'Téléphone');

        yield TextareaField::new('comment', 'Commentaire');

    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->accountManager->sync($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->accountManager->sync($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            // filtres par classement / interfacs / interclubs / cours : voir les inscriptions (PlayerSeasonCrudController)
            ->add(ChoiceFilter::new('gender')
                ->setChoices(Gender::getTranslatableChoices())
            )
            // Does not work out of the box because it is the inverse side
            // Would require to flip the association side, or a custom filter with an explicit join workaround,
            // which is a bit much if we don't really need this filter.
            // ->add(NullFilter::new('user')->setChoiceLabels('Not null', 'Null'))
        ;
    }

}
