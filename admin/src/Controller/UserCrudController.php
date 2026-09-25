<?php

namespace Admin\Controller;

use App\Entity\User;
use App\Service\PlayerAccountManager;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MANAGER')]
class UserCrudController extends AbstractCrudController
{

    public function __construct(
        private PlayerAccountManager $accountManager,
    )
    {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('User')
            ->setEntityLabelInPlural('Users')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield DateTimeField::new('createdAt')->hideOnForm();
        yield TextField::new('name');
        yield EmailField::new('email');
        yield BooleanField::new('verified')->renderAsSwitch(false);
        yield BooleanField::new('enabled')->renderAsSwitch(false);
        yield DateTimeField::new('lastLogin')->hideOnForm();
        yield ChoiceField::new('roles')
            ->setChoices([
                'User' => 'ROLE_USER',
                'Admin' => 'ROLE_ADMIN',
                'Editor' => 'ROLE_EDITOR',
                'Manager' => 'ROLE_MANAGER',
                'Super Admin' => 'ROLE_SUPER_ADMIN',
            ])
            ->allowMultipleChoices()
            ->renderExpanded();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->setPermission(Action::NEW, 'ROLE_SUPER_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN')
            ->setPermission(Action::EDIT, 'ROLE_SUPER_ADMIN');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('email'))
            ->add(TextFilter::new('name'))
            ->add(BooleanFilter::new('verified'))
        ;
    }

    // Compte créé depuis l'admin : mot de passe aléatoire, actif, vérifié (PlayerAccountManager)
    public function createEntity(string $entityFqcn): User
    {
        return $this->accountManager->newUser();
    }


}
