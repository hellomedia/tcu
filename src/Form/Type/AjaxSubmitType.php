<?php 

namespace App\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Normally, for a custom form type, we are supposed to extend AbstractType
 * and set the parent to what we want to extendw ith getParent()
 * 
 * BUT WITH SUBMIT, which is an unusual form type (not a data type),
 * the conventional approach does not work.
 * 
 * It gives the error "Can't get a way to read the property "foo" in class "App\Entity\Booking".
 * Because it doesn't understand the field is not mapped, and if you try to add "mapped" => false,
 * it does not handle it.
 * 
 * Basically, extending AbstractType and setting the parent as SubmitType is not enough to
 * get the internal SubmitType behaviour.
 * 
 * SOLUTION:
 * If we extend SubmitType, we get the correct behaviour !
 * 
 * ---------------------------------
 * This field goes together with
 *  form theme {% block ajax_submit_widget %}
 *  ajax_submit.css
 */
final class AjaxSubmitType extends SubmitType
{
    public function getParent(): ?string
    {
        return SubmitType::class;
    }

    public function getBlockPrefix(): string
    {
        // this defines the Twig block name: save_button_widget
        return 'ajax_submit';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => 'Sauver',
            'attr' => [
                'class' => 'btn btn-primary btn-lg',
                'data-ajax-target' => 'submitBtn',
            ],
        ]);
    }
}