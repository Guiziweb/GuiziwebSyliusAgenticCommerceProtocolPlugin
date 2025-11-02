<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

/**
 * Gateway configuration form for ACP
 *
 * ACP Spec: openapi.agentic_checkout.yaml
 * Supports delegate payment (currently Stripe as per spec, but extensible)
 */
final class ACPGatewayConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('psp_url', UrlType::class, [
                'label' => 'guiziweb.form.acp_gateway.psp_url',
                'help' => 'guiziweb.form.acp_gateway.psp_url_help',
                'constraints' => [
                    new NotBlank([
                        'groups' => ['sylius'],
                    ]),
                    new Url([
                        'groups' => ['sylius'],
                    ]),
                ],
            ])
            ->add('psp_merchant_secret_key', TextType::class, [
                'label' => 'guiziweb.form.acp_gateway.psp_merchant_secret_key',
                'help' => 'guiziweb.form.acp_gateway.psp_merchant_secret_key_help',
                'constraints' => [
                    new NotBlank([
                        'groups' => ['sylius'],
                    ]),
                ],
            ])

            ->add('webhook_url', UrlType::class, [
                'label' => 'guiziweb.form.acp_gateway.webhook_url',
                'help' => 'guiziweb.form.acp_gateway.webhook_url_help',
                'constraints' => [
                    new NotBlank([
                        'groups' => ['sylius'],
                    ]),
                    new Url([
                        'groups' => ['sylius'],
                    ]),
                ],
            ])
            ->add('webhook_secret', TextType::class, [
                'label' => 'guiziweb.form.acp_gateway.webhook_secret',
                'help' => 'guiziweb.form.acp_gateway.webhook_secret_help',
                'constraints' => [
                    new NotBlank([
                        'groups' => ['sylius'],
                    ]),
                ],
            ])

            ->add('bearer_token', TextType::class, [
                'label' => 'guiziweb.form.acp_gateway.bearer_token',
                'help' => 'guiziweb.form.acp_gateway.bearer_token_help',
                'constraints' => [
                    new NotBlank([
                        'groups' => ['sylius'],
                    ]),
                ],
            ])
            ->add('signature_secret', TextType::class, [
                'label' => 'guiziweb.form.acp_gateway.signature_secret',
                'help' => 'guiziweb.form.acp_gateway.signature_secret_help',
                'required' => false,
            ])
        ;
    }
}
