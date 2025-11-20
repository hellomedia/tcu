<?php

namespace Pack\Security\Form;

use App\Entity\User;
use Pack\Security\Form\Field\HoneypotField;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;

class RegistrationForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'required' => true,
                'attr' => ['autocomplete' => 'username'],
            ])
            ->add('plainPassword', PasswordType::class, [
                // instead of being set onto the object,
                // this is read and encoded in the controller
                'mapped' => false,
                'label' => 'Password',
                'required' => true,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'data-password-visibility-target' => 'input',
                    'spellcheck' => false,
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a password',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Your password should be at least {{ limit }} characters',
                        // max length allowed by Symfony for security reasons
                        'max' => 4096,
                    ]),
                ],
            ])
            ->add('occupation', HoneypotField::class)
            ->add('terms', CheckboxType::class, [
                'label' => 'Accept the terms',
                'required' => true,
                'mapped' => false,
                'constraints' => [
                    new IsTrue([
                        'message' => 'You should agree to our terms.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'validation_groups' => ['registration'],
        ]);
    }
}
