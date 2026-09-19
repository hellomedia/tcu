<?php

namespace Admin\Controller;

use App\Entity\Season;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Création / modification d'une saison
 * La liste des saisons et les actions (saison courante, pré-remplir les inscriptions) : SeasonController
 */
class SeasonCrudController extends AbstractCrudController
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator
    )
    {
    }

    public static function getEntityFqcn(): string
    {
        return Season::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Saison')
            ->setEntityLabelInPlural('Saisons')
            ->setEntityPermission('ROLE_MANAGER')
            ->setDefaultSort([
                'startsOn' => 'DESC',
            ])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        // Supprimer une saison supprimerait ses poules, ses matchs et ses inscriptions
        return $actions
            ->disable(Action::DELETE)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Saison')
            ->hideOnForm();

        yield ChoiceField::new('type', 'Type')
            ->onlyOnForms();

        yield IntegerField::new('year', 'Année')
            ->setHelp('Année de début. Hiver 2026-2027 => 2026')
            ->onlyOnForms();

        yield DateField::new('startsOn', 'Début')
            ->setRequired(false)
            ->setHelp('Laisser vide pour les dates par défaut. Été : 1er avril - 31 octobre. Hiver : 1er novembre - 31 mars.');

        yield DateField::new('endsOn', 'Fin')
            ->setRequired(false);

        yield BooleanField::new('current', 'Saison courante')
            ->renderAsSwitch(false)
            ->hideOnForm();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var Season $entityInstance */
        $entityInstance->initDefaultDates();

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var Season $entityInstance */
        $entityInstance->initDefaultDates();

        parent::updateEntity($entityManager, $entityInstance);
    }

    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        // NB: $action est la page (new / edit). Le bouton utilisé est dans la requête.
        $submitButtonName = $context->getRequest()->request->all()['ea']['newForm']['btn'] ?? null;

        if ($submitButtonName === Action::SAVE_AND_RETURN) {
            return new RedirectResponse($this->urlGenerator->generate('admin_seasons'));
        }

        return parent::getRedirectResponseAfterSave($context, $action);
    }
}
