<?php

namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CompetitionFormFieldType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Field Name/Identifier',
                'help' => 'Unique identifier for this field (e.g., "user_age", "contact_preference"). This is used internally.',
                'attr' => ['placeholder' => 'e.g., contact_email'],
            ])
            ->add('label', TextType::class, [
                'label' => 'Display Label',
                'help' => 'The label shown to the user next to the input field.',
                'attr' => ['placeholder' => 'e.g., Your Email Address'],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Field Type',
                'choices' => [
                    'Text (Single Line)' => 'text',
                    'Text (Multi-Line/Textarea)' => 'textarea',
                    'Email' => 'email',
                    'Telephone' => 'tel',
                    'Number' => 'number',
                    'Date' => 'date',
                    'Checkbox' => 'checkbox',
                ],
                'help' => 'Determines the kind of input field displayed to the end-user.',
            ])
            // ->add('defaultValue', TextType::class, [
            //     'label' => 'Default Value (Optional)',
            //     'required' => false,
            //     'help' => 'Pre-filled value for this field when a user views the submission form.',
            // ])
            ;
        // You could expand this with more options like 'required' (boolean), 'placeholder', 'options' (for select/radio types) etc.
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // No data_class is set, as this form type is meant to map to an array structure.
        // EasyAdmin's CollectionField will handle mapping this to elements of the formFields array.
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}