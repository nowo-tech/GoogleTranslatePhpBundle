<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Demo form: text to translate (REQ-DEMO-015: a FormKit form, profile `demo`).
 * The form carries the stateless CSRF token (`csrf.yaml`) like every demo form.
 *
 * @extends AbstractType<array{text?: string|null}>
 */
#[FormKitConfig('demo')]
final class TranslateFormType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addTextarea($builder, 'text', [
            'label'       => 'Text to translate (auto-detect → Spanish)',
            'placeholder' => false,
            'help'        => false,
            'attr'        => ['rows' => 4],
        ]);
        $this->addWithDefaults($builder, 'submit', SubmitType::class, [
            'label' => 'Translate',
            'attr'  => ['class' => 'nowo-ui-btn nowo-ui-btn--primary'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'             => 'POST',
            'translation_domain' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
