<?php

namespace Admin\Controller;

use App\Entity\Interface\EntityInterface;
use App\Repository\SeasonRepository;
use App\Service\SeasonContext;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\Font;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\GrayScale;
use EasyCorp\Bundle\EasyAdminBundle\Config\Theme;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AdminDashboard(routePath: '/', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private SeasonRepository $seasonRepository,
        private SeasonContext $seasonContext,
    ) {
    }

    public function index(): Response
    {        
        return $this->render('@admin/dashboard.html.twig', []);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Interfacs')
            // the look of EasyAdmin before its 5.9 redesign (blue, slate grays, system font);
            // links keep the primary color in admin-variables.css
            // https://github.com/EasyCorp/EasyAdminBundle/blob/5.x/UPGRADE.md
            ->setTheme(Theme::new()
                ->primaryColor('hsl(230, 61%, 58%)', dark: 'hsl(224, 84%, 49%)')
                ->grays(GrayScale::SLATE, dark: GrayScale::NEUTRAL)
                ->radius('xs')
                ->font(base: Font::System, monospace: Font::System))
        ;
    }

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->setDateTimeFormat('d/MM/yy HH:mm') // https://unicode-org.github.io/icu/userguide/format_parse/datetime/
            ->setPaginatorPageSize(100)
            ->setDefaultSort(['id' => 'DESC'])
            ->setAutofocusSearch()
            ->showEntityActionsInlined()
            ->setFormThemes(['@admin/form/form_theme.html.twig'])
            ->setPageTitle(Crud::PAGE_DETAIL, static function (EntityInterface $entity) {
                return $entity;
            })
            ->setPageTitle(Crud::PAGE_EDIT, static function (EntityInterface $entity) {
                return 'Modifier ' . $entity;
            })
        ;
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            // imports the given entrypoint defined in the importmap.php file of AssetMapper
            // it's equivalent to adding this inside the <head> element:
            // {{ importmap('admin') }}
            ->addAssetMapperEntry('admin')
            // you can also import multiple entries
            // it's equivalent to calling {{ importmap(['app', 'admin']) }}
            //->addAssetMapperEntry('app', 'admin')
        ;
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-dashboard');

        yield MenuItem::subMenu('Status', 'fa fa-dashboard')
            ->setPermission('ROLE_SUPER_ADMIN')
            ->setSubItems([
                MenuItem::linkToUrl('Opcache', 'fa fa-dashboard', $this->urlGenerator->generate('admin_status_opcache')),
                MenuItem::linkToUrl('Realpath cache', 'fa fa-dashboard', $this->urlGenerator->generate('admin_status_realpath_cache')),
                MenuItem::linkToUrl('Php-fpm', 'fa fa-dashboard', '/status/php-fpm'),
                MenuItem::linkToUrl('Php info', 'fa fa-info', $this->urlGenerator->generate('admin_status_phpinfo')),
            ]);

        // Saison sur laquelle on travaille dans l'admin : poules, inscriptions, planning
        $selected = $this->seasonContext->getSelected();
        $seasonItems = [];
        foreach ($this->seasonRepository->findAll() as $season) {
            $seasonItems[] = MenuItem::linkToUrl(
                $season . ($season->isCurrent() ? ' (courante)' : ''),
                $season === $selected ? 'fa fa-check' : 'fa fa-angle-right',
                $this->urlGenerator->generate('admin_season_select', ['id' => $season->getId()])
            );
        }
        $seasonItems[] = MenuItem::linkToUrl('Gérer les saisons', 'fa fa-gear', $this->urlGenerator->generate('admin_seasons'));

        yield MenuItem::section('Saison')
            ->setPermission('ROLE_EDITOR');
        yield MenuItem::subMenu($selected ? (string) $selected : 'Aucune saison', 'fa fa-sun')
            ->setPermission('ROLE_EDITOR')
            ->setSubItems($seasonItems);
        yield MenuItem::linkTo(RegistrationCrudController::class, 'Inscriptions', 'fa fa-clipboard-list')
            ->setPermission('ROLE_EDITOR');

        yield MenuItem::section('Interfacs')
            ->setPermission('ROLE_EDITOR');
        yield MenuItem::linkToUrl('Poules', 'fa fa-group', $this->urlGenerator->generate('admin_groups'))
            ->setPermission('ROLE_EDITOR');
        yield MenuItem::linkToUrl('Planning par poules', 'fa fa-calendar', $this->urlGenerator->generate('admin_planning_groups'))
            ->setPermission('ROLE_EDITOR');
        yield MenuItem::linkToUrl('Planning par dates', 'fa fa-calendar', $this->urlGenerator->generate('admin_planning'))
            ->setPermission('ROLE_EDITOR');
        yield MenuItem::linkToUrl('Matchs passés', 'fa fa-calendar', $this->urlGenerator->generate('admin_planning_past'))
            ->setPermission('ROLE_EDITOR');
        yield MenuItem::linkToUrl('Entraînements', 'fa fa-table-tennis-paddle-ball', $this->urlGenerator->generate('admin_practices'))
            ->setPermission('ROLE_EDITOR');
        yield MenuItem::linkToUrl('Notifications', 'fa fa-envelope', $this->urlGenerator->generate('admin_planning_notifications'))
            ->setPermission('ROLE_EDITOR');
        yield MenuItem::linkToUrl('Dispos', 'fa fa-calendar-check', $this->urlGenerator->generate('admin_availabilities'))
            ->setPermission('ROLE_EDITOR');
        yield MenuItem::linkToUrl('Invitations', 'fa fa-envelope-open-text', $this->urlGenerator->generate('admin_invitations'))
            ->setPermission('ROLE_EDITOR');

        yield MenuItem::section('Club')
            ->setPermission('ROLE_EDITOR');
        yield MenuItem::linkTo(PlayerCrudController::class, 'Joueurs', 'fa fa-user')
            ->setPermission('ROLE_EDITOR');

        yield MenuItem::section('Admin')
            ->setPermission('ROLE_MANAGER');
        yield MenuItem::linkToUrl('Plages horaires', 'fa fa-calendar', $this->urlGenerator->generate('admin_planning_slots'))
            ->setPermission('ROLE_MANAGER');
        yield MenuItem::linkTo(DateCrudController::class, 'Dates', 'fa fa-calendar-day')
            ->setPermission('ROLE_MANAGER');
        yield MenuItem::linkTo(CourtCrudController::class, 'Terrains', 'fa fa-calendar-day')
            ->setPermission('ROLE_MANAGER');
        yield MenuItem::linkTo(AdminCrudController::class, 'Admins', 'fa fa-user')
            ->setPermission('ROLE_MANAGER');
        
        yield MenuItem::section('Dev Admin')
            ->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkTo(InterfacMatchCrudController::class, 'Matchs', 'fa fa-trophy')
            ->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkTo(BookingCrudController::class, 'Réservations', 'fa fa-calendar')
            ->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkTo(SlotCrudController::class, 'Créneaux', 'fa fa-calendar')
            ->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkTo(UserCrudController::class, 'Users', 'fa fa-user')
            ->setPermission('ROLE_SUPER_ADMIN');
    }

    public function configureActions(): Actions
    {
        return Actions::new()
            ->disable(Action::BATCH_DELETE)

            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DELETE)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, Action::DELETE])

            ->add(Crud::PAGE_DETAIL, Action::DELETE)
            ->add(Crud::PAGE_DETAIL, Action::INDEX)
            ->add(Crud::PAGE_DETAIL, Action::EDIT)

            ->add(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN)
            ->update(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN, function (Action $action) {
                return $action->setLabel('Sauver → List');
            })
            ->add(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->update(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE, function (Action $action) {
                return $action->setLabel('Sauver → Edit')->setIcon(null);
            })
            ->add(Crud::PAGE_EDIT, Action::INDEX)
            ->add(Crud::PAGE_EDIT, Action::DETAIL)

            ->add(Crud::PAGE_NEW, Action::SAVE_AND_RETURN)
            ->add(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->add(Crud::PAGE_NEW, Action::INDEX)
        ;
    }

    public function configureFilters(): Filters
    {
        return Filters::new()
            ->add('id')
        ;
    }
}
