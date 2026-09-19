<?php

namespace Admin\Controller;

use App\Entity\Player;
use App\Service\SeasonContext;
use App\Enum\Gender;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

class PlayerCrudController extends AbstractCrudController
{
    public function __construct(
        private SeasonContext $seasonContext,
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
            ->setDefaultSort([
                'lastname' => 'ASC',
            ])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('lastname', 'Nom');
        yield TextField::new('firstname', 'Prénom');

        yield TextField::new('affiliationNumber', 'N° d\'affiliation')
            ->setHelp('Numéro d\'affiliation à la fédération. Permet de récupérer le classement du joueur.');

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

        yield TextareaField::new('availabilities', 'Dispos');
        yield TextareaField::new('comment', 'Commentaire');

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
