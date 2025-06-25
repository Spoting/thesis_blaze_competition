<?php

// src/Form/Public/SubmissionType.php
namespace App\Form\Public;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SubmissionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $formFieldsConfig = $options['form_fields'] ?? [];

        $builder
            ->add('competition_id', HiddenType::class, [
                'mapped' => false,
                'data' => $options['competition_id'],
            ]);

        foreach ($formFieldsConfig as $fieldConfig) {
            $fieldName = $fieldConfig['name'] ?? null;
            $fieldType = $fieldConfig['type'] ?? 'text';
            $fieldLabel = $fieldConfig['label'] ?? $fieldName;
            $fieldOptions = ['label' => $fieldLabel];

            switch ($fieldType) {
                case 'email':
                    $builder->add($fieldName, EmailType::class, $fieldOptions);
                    break;
                case 'tel':
                    $builder->add($fieldName, TelType::class, $fieldOptions);
                    break;
                // TODO: Add More.
                default:
                    $builder->add($fieldName, TextType::class, $fieldOptions);
                    break;
            }

            
        }
        $builder->add('submit', SubmitType::class, ['label' => 'Υποβολή Συμμετοχής']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'competition_id' => null,
            'form_fields' => [],
        ]);
        $resolver->setRequired(['competition_id', 'form_fields']);
        $resolver->setAllowedTypes('competition_id', 'int');
        $resolver->setAllowedTypes('form_fields', 'array');
    }
}