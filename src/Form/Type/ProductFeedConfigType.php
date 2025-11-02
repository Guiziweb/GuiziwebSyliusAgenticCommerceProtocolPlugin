<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Form\Type;

use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;

final class ProductFeedConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('channel', ChannelChoiceType::class, [
                'label' => 'sylius.ui.channel',
                'required' => true,
            ])
            ->add('feedEndpoint', UrlType::class, [
                'label' => 'guiziweb.ui.feed_endpoint',
                'required' => false,
                'attr' => [
                    'placeholder' => 'https://api.openai.com/v1/commerce/products',
                ],
            ])
            ->add('feedBearerToken', TextType::class, [
                'label' => 'guiziweb.ui.feed_bearer_token',
                'required' => false,
                'attr' => [
                    'placeholder' => 'sk_...',
                ],
            ])
            ->add('defaultBrand', TextType::class, [
                'label' => 'guiziweb.ui.default_brand',
                'required' => false,
            ])
            ->add('defaultWeight', TextType::class, [
                'label' => 'guiziweb.ui.default_weight',
                'required' => false,
                'attr' => [
                    'placeholder' => '1.5 kg',
                ],
            ])
            ->add('defaultMaterial', TextType::class, [
                'label' => 'guiziweb.ui.default_material',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Cotton',
                ],
            ])
            ->add('returnPolicyUrl', UrlType::class, [
                'label' => 'guiziweb.ui.return_policy_url',
                'required' => false,
            ])
            ->add('returnWindowDays', IntegerType::class, [
                'label' => 'guiziweb.ui.return_window_days',
                'required' => false,
                'attr' => [
                    'min' => 0,
                ],
            ])
            ->add('privacyPolicyUrl', UrlType::class, [
                'label' => 'guiziweb.ui.privacy_policy_url',
                'required' => false,
            ])
            ->add('termsOfServiceUrl', UrlType::class, [
                'label' => 'guiziweb.ui.terms_of_service_url',
                'required' => false,
            ])
        ;
    }
}
