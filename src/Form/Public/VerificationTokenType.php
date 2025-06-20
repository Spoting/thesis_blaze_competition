<?php 
namespace App\Form\Public;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;

class VerificationTokenType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('token', TextType::class, [
                'label' => 'Verification Code',
                'attr' => [
                    'placeholder' => 'Enter the code from your email',
                    'maxlength' => 36, // UUID length
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a verification code.',
                    ]),
                    new Length([
                        'min' => 36, // Assuming UUID v4
                        'max' => 36,
                        'exactMessage' => 'The verification code must be exactly {{ limit }} characters long.',
                    ]),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Verify',
                'attr' => ['class' => 'btn btn-primary'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your default options here
        ]);
    }
}