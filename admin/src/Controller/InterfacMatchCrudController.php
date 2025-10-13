<?php

namespace Admin\Controller;

use App\Entity\InterfacMatch;
use App\Entity\Player;
use App\Enum\MatchFormat;
use App\Enum\Side;
use Closure;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
class InterfacMatchCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return InterfacMatch::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Match')
            ->setEntityLabelInPlural('Matchs')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();

        yield ChoiceField::new('format')
            ->setFormTypeOptions([
                'attr' => [
                    'data-action' => 'change->match-format#update',
                ],
        ]);
        
        yield AssociationField::new('participants', 'Joueurs')
            ->hideOnForm()
            ->setFormTypeOption('by_reference', false)
            ->setTemplatePath('@admin/field/participants.html.twig')
        ;

        yield AssociationField::new('group');

        // your match fields (date, status, etc.) …
        yield FormField::addPanel('Joueurs');

        // These are UNMAPPED; we’ll handle them in the form builder below.
        yield Field::new('playerA1', 'Joueur A')
            ->onlyOnForms()
            ->setFormType(EntityType::class)
            ->setFormTypeOptions([
                'class' => Player::class,
                'mapped' => false,
                'required' => true,
                'placeholder' => 'Choisir...',
                'autocomplete' => true,
                'multiple' => false,
            ]);
        
        // visible if doubles
        yield Field::new('playerA2', 'Joueur A #2')
            ->onlyOnForms()
            ->setFormType(EntityType::class)
            ->setFormTypeOptions([
                'class' => Player::class,
                'mapped' => false,
                'required' => false,
                'autocomplete' => true,
                'multiple' => false,
                'row_attr' => ['data-match-format-target' => 'doublesOnly'],
            ]);

        yield Field::new('playerB1', 'Joueur B')
            ->onlyOnForms()
            ->setFormType(EntityType::class)
            ->setFormTypeOptions([
                'class' => Player::class,
                'mapped' => false,
                'required' => true,
                'placeholder' => 'Choisir...',
                'autocomplete' => true,
                'multiple' => false,
            ]);

        // visibile if doubles
        yield Field::new('playerB2', 'Joueur B #2')
            ->onlyOnForms()
            ->setFormType(EntityType::class)
            ->setFormTypeOptions([
                'class' => Player::class,
                'mapped' => false,
                'required' => false,
                'autocomplete' => true,
                'multiple' => false,
                'row_attr' => ['data-match-format-target' => 'doublesOnly'],
        ]);

        yield FormField::addPanel('Programmation');

        yield AssociationField::new('booking', 'Créneau');
    }

    public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $attr = $formOptions->get('attr') ?? [];
        $attr['data-controller'] = trim(($attr['data-controller'] ?? '') . ' match-format');
        $formOptions->set('attr', $attr);

        $builder = parent::createNewFormBuilder($entityDto, $formOptions, $context);

        // On submit, convert the chosen players into MatchParticipant rows
        $builder->addEventListener(FormEvents::POST_SUBMIT, $this->postSubmitListener());

        return $builder;
    }

    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $attr = $formOptions->get('attr') ?? [];
        $attr['data-controller'] = trim(($attr['data-controller'] ?? '') . ' match-format');
        $formOptions->set('attr', $attr);

        $builder = parent::createEditFormBuilder($entityDto, $formOptions, $context);

        /** @var InterfacMatch $match */
        $match = $entityDto->getInstance();

        /* prefill unmapped fields */
        $a = $match->getPlayersForSide(Side::A);
        $b = $match->getPlayersForSide(Side::B);

        $builder->get('playerA1')->setData($a[0] ?? null);
        $builder->get('playerA2')->setData($a[1] ?? null);
        $builder->get('playerB1')->setData($b[0] ?? null);
        $builder->get('playerB2')->setData($b[1] ?? null);

        // On submit, convert the chosen players into MatchParticipant rows
        $builder->addEventListener(FormEvents::POST_SUBMIT, $this->postSubmitListener());

        return $builder;
    }

    /**
     * Convert chosen players into MatchParticipant rows
     */
    private function postSubmitListener(): Closure
    {
        return function (FormEvent $event) {

            /** @var InterfacMatch $match */
            $match = $event->getData();
            $form  = $event->getForm();

            $format = $match->getFormat();

            $a1 = $form->get('playerA1')->getData();
            $a2 = $form->get('playerA2')->getData();
            $b1 = $form->get('playerB1')->getData();
            $b2 = $form->get('playerB2')->getData();

            // Validation
            $players = array_filter([$a1, $a2, $b1, $b2]);
            if (count($players) !== count(array_unique($players, \SORT_REGULAR))) {
                $form->addError(new FormError('Un joueur ne peut pas apparaître 2 fois.'));
                return;
            }

            if ($format === MatchFormat::SINGLES) {

                if (!$a1 || !$b1) {
                    $form->addError(new FormError('Un simple doit avoir 2 joueurs.'));
                    return;
                }
                if ($a2 || $b2) {
                    $a2 = null;
                    $b2 = null;
                }
                $match->replaceParticipantsForSide(Side::A, $a1);
                $match->replaceParticipantsForSide(Side::B, $b1);
            }

            if ($format == MatchFormat::DOUBLES) {

                if (!$a1 || !$a2 || !$b1 || !$b2) {
                    $form->addError(new FormError('Un double doit avoir 2 joueurs par équipe.'));
                    return;
                }
                $match->replaceParticipantsForSide(Side::A, $a1, $a2);
                $match->replaceParticipantsForSide(Side::B, $b1, $b2);
            }
        };
    }
}
