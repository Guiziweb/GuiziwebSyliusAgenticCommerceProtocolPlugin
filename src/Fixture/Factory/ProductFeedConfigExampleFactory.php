<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Fixture\Factory;

use Faker\Factory;
use Faker\Generator;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Entity\ProductFeedConfig;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Bundle\CoreBundle\Fixture\OptionsResolver\LazyOption;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Factory for creating ProductFeedConfig fixtures
 *
 * @implements ExampleFactoryInterface<ProductFeedConfig>
 */
final class ProductFeedConfigExampleFactory extends AbstractExampleFactory implements ExampleFactoryInterface
{
    private Generator $faker;

    private OptionsResolver $optionsResolver;

    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     *
     * @phpstan-ignore-next-line
     */
    public function __construct(
        private readonly FactoryInterface $productFeedConfigFactory,
        private readonly ChannelRepositoryInterface $channelRepository,
    ) {
        $this->faker = Factory::create();
        $this->optionsResolver = new OptionsResolver();

        $this->configureOptions($this->optionsResolver);
    }

    public function create(array $options = []): ProductFeedConfig
    {
        $options = $this->optionsResolver->resolve($options);

        /** @var ProductFeedConfig $config */
        $config = $this->productFeedConfigFactory->createNew();

        $config->setChannel($options['channel']);
        $config->setFeedEndpoint($options['feed_endpoint']);
        $config->setFeedBearerToken($options['feed_bearer_token']);
        $config->setDefaultBrand($options['default_brand']);
        $config->setDefaultWeight($options['default_weight']);
        $config->setDefaultMaterial($options['default_material']);
        $config->setReturnPolicyUrl($options['return_policy_url']);
        $config->setReturnWindowDays($options['return_window_days']);
        $config->setPrivacyPolicyUrl($options['privacy_policy_url']);
        $config->setTermsOfServiceUrl($options['terms_of_service_url']);

        return $config;
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('channel', LazyOption::randomOne($this->channelRepository))
            ->setAllowedTypes('channel', ['null', 'string', ChannelInterface::class])
            ->setNormalizer('channel', LazyOption::findOneBy($this->channelRepository, 'code'))

            ->setDefault('feed_endpoint', fn (Options $options): string => 'https://api.openai.com/v1/commerce/products')
            ->setAllowedTypes('feed_endpoint', 'string')

            ->setDefault('feed_bearer_token', fn (Options $options): string => 'test_bearer_token_' . $this->faker->uuid())
            ->setAllowedTypes('feed_bearer_token', 'string')

            ->setDefault('default_brand', fn (Options $options): string => $this->faker->company())
            ->setAllowedTypes('default_brand', ['null', 'string'])

            ->setDefault('default_weight', null)
            ->setAllowedTypes('default_weight', ['null', 'string'])

            ->setDefault('default_material', null)
            ->setAllowedTypes('default_material', ['null', 'string'])

            ->setDefault('return_policy_url', fn (Options $options): string => $this->faker->url())
            ->setAllowedTypes('return_policy_url', ['null', 'string'])

            ->setDefault('return_window_days', 30)
            ->setAllowedTypes('return_window_days', ['null', 'int'])

            ->setDefault('privacy_policy_url', fn (Options $options): string => $this->faker->url())
            ->setAllowedTypes('privacy_policy_url', ['null', 'string'])

            ->setDefault('terms_of_service_url', fn (Options $options): string => $this->faker->url())
            ->setAllowedTypes('terms_of_service_url', ['null', 'string'])
        ;
    }
}
