<?php

namespace Admin\Controller;

use App\Entity\Booking;
use App\Entity\Court;
use App\Entity\Date;
use App\Entity\Group;
use App\Entity\Interface\EntityInterface;
use App\Entity\InterfacMatch;
use App\Entity\Player;
use App\Entity\Slot;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AdminDashboard(routePath: '/', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    // route attribute here is deprecated and should be removed in next version
    // but at the moment, removing it cause error: 'missing admin_dashboard' route
    #[Route('/', name: 'admin_dashboard')]
    public function index(): Response
    {        
        return $this->render('@admin/dashboard.html.twig', []);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Interfacs')
        ;
    }

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->setDateTimeFormat('d/MM/yy HH:mm') // https://unicode-org.github.io/icu/userguide/format_parse/datetime/
            ->setPaginatorPageSize('100')
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

        yield MenuItem::section('Joueurs')
            ->setPermission('ROLE_EDITOR');
        yield MenuItem::linkToCrud('Joueurs', 'fa fa-user', Player::class)
            ->setPermission('ROLE_EDITOR');
        yield MenuItem::linkToCrud('Poules', 'fa fa-group', Group::class)
            ->setPermission('ROLE_EDITOR');

        yield MenuItem::section('Planning')
            ->setPermission('ROLE_EDITOR');
        yield MenuItem::linkToUrl('Poules', 'fa fa-calendar', $this->urlGenerator->generate('admin_planning_groups'))
            ->setPermission('ROLE_EDITOR');
        yield MenuItem::linkToUrl('Planning', 'fa fa-calendar', $this->urlGenerator->generate('admin_planning'))
            ->setPermission('ROLE_EDITOR');
        yield MenuItem::linkToUrl('Matchs passés', 'fa fa-calendar', $this->urlGenerator->generate('admin_planning_past'))
            ->setPermission('ROLE_EDITOR');
        
        yield MenuItem::section('Admin')
            ->setPermission('ROLE_MANAGER');
        yield MenuItem::linkToUrl('Plages horaires', 'fa fa-calendar', $this->urlGenerator->generate('admin_planning_slots'))
            ->setPermission('ROLE_MANAGER');
        yield MenuItem::linkToCrud('Dates', 'fa fa-calendar-day', Date::class)
            ->setPermission('ROLE_MANAGER');
        yield MenuItem::linkToCrud('Terrains', 'fa fa-calendar-day', Court::class)
            ->setPermission('ROLE_MANAGER');
        yield MenuItem::linkToCrud('Admins', 'fa fa-user', User::class)
            ->setPermission('ROLE_MANAGER');
        
        yield MenuItem::section('Dev Admin')
            ->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToCrud('Matchs', 'fa fa-trophy', InterfacMatch::class)
            ->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToCrud('Réservations', 'fa fa-calendar', Booking::class)
            ->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToCrud('Créneaux', 'fa fa-calendar', Slot::class)
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
                return $action->setLabel('Sauver → Edit')->setIcon(false);
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
