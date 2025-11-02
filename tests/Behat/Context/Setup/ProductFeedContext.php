<?php

declare(strict_types=1);

namespace Tests\Guiziweb\SyliusAgenticCommerceProtocolPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\Persistence\ObjectManager;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Entity\ProductFeedConfig;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * Behat context for setting up ProductFeedConfig in tests
 */
final class ProductFeedContext implements Context
{
    /**
     * @param FactoryInterface<ProductFeedConfig> $productFeedConfigFactory
     * @param RepositoryInterface<ProductFeedConfig> $productFeedConfigRepository
     */
    public function __construct(
        private SharedStorageInterface $sharedStorage,
        private FactoryInterface $productFeedConfigFactory,
        private RepositoryInterface $productFeedConfigRepository,
        private ObjectManager $objectManager,
    ) {
    }

    /**
     * @Given the channel has product feed configuration
     * @Given the channel :channel has product feed configuration
     */
    public function theChannelHasProductFeedConfiguration(?string $channelCode = null): void
    {
        /** @var ChannelInterface $channel */
        $channel = $channelCode !== null
            ? $this->sharedStorage->get('channel_' . $channelCode)
            : $this->sharedStorage->get('channel');

        $config = $this->createProductFeedConfig($channel, [
            'feed_endpoint' => 'https://api.openai.com/v1/commerce/products',
            'feed_bearer_token' => 'test_bearer_token_123',
            'default_brand' => 'Test Brand',
            'return_window_days' => 30,
        ]);

        $this->saveProductFeedConfig($config);
    }

    /**
     * @Given the channel has product feed configuration with brand :brand
     */
    public function theChannelHasProductFeedConfigurationWithBrand(string $brand): void
    {
        /** @var ChannelInterface $channel */
        $channel = $this->sharedStorage->get('channel');

        $config = $this->createProductFeedConfig($channel, [
            'feed_endpoint' => 'https://api.openai.com/v1/commerce/products',
            'feed_bearer_token' => 'test_bearer_token_123',
            'default_brand' => $brand,
            'return_window_days' => 30,
        ]);

        $this->saveProductFeedConfig($config);
    }

    /**
     * @Given the channel has product feed configuration with return policy
     */
    public function theChannelHasProductFeedConfigurationWithReturnPolicy(): void
    {
        /** @var ChannelInterface $channel */
        $channel = $this->sharedStorage->get('channel');

        $config = $this->createProductFeedConfig($channel, [
            'feed_endpoint' => 'https://api.openai.com/v1/commerce/products',
            'feed_bearer_token' => 'test_bearer_token_123',
            'default_brand' => 'Test Brand',
            'return_policy_url' => 'https://example.com/returns',
            'return_window_days' => 30,
            'privacy_policy_url' => 'https://example.com/privacy',
            'terms_of_service_url' => 'https://example.com/terms',
        ]);

        $this->saveProductFeedConfig($config);
    }

    /**
     * @Given the channel has feed bearer token :token
     */
    public function theChannelHasFeedBearerToken(string $token): void
    {
        /** @var ChannelInterface $channel */
        $channel = $this->sharedStorage->get('channel');

        // Remove existing config if any
        $existingConfig = $this->productFeedConfigRepository->findOneBy(['channel' => $channel]);
        if ($existingConfig !== null) {
            $this->productFeedConfigRepository->remove($existingConfig);
            $this->objectManager->flush();
        }

        $config = $this->createProductFeedConfig($channel, [
            'feed_endpoint' => 'https://api.openai.com/v1/commerce/products',
            'feed_bearer_token' => $token,
            'default_brand' => 'Test Brand',
        ]);

        $this->saveProductFeedConfig($config);
    }

    /**
     * @Given the channel has no product feed configuration
     */
    public function theChannelHasNoProductFeedConfiguration(): void
    {
        /** @var ChannelInterface $channel */
        $channel = $this->sharedStorage->get('channel');

        // Remove existing config if any
        $existingConfig = $this->productFeedConfigRepository->findOneBy(['channel' => $channel]);
        if ($existingConfig !== null) {
            $this->productFeedConfigRepository->remove($existingConfig);
            $this->objectManager->flush();
        }
    }

    /**
     * @Given the store operates on a single channel in :countryName with code :channelCode
     */
    public function theStoreOperatesOnASingleChannelWithCode(string $countryName, string $channelCode): void
    {
        // This will be handled by Sylius base contexts
        // We just need to store the channel code mapping
        if ($this->sharedStorage->has('channel')) {
            $channel = $this->sharedStorage->get('channel');
            $this->sharedStorage->set('channel_' . $channelCode, $channel);
        }
    }

    /**
     * Create ProductFeedConfig with given options
     */
    private function createProductFeedConfig(ChannelInterface $channel, array $options): ProductFeedConfig
    {
        /** @var ProductFeedConfig $config */
        $config = $this->productFeedConfigFactory->createNew();
        $config->setChannel($channel);

        if (isset($options['feed_endpoint'])) {
            $config->setFeedEndpoint($options['feed_endpoint']);
        }

        if (isset($options['feed_bearer_token'])) {
            $config->setFeedBearerToken($options['feed_bearer_token']);
        }

        if (isset($options['default_brand'])) {
            $config->setDefaultBrand($options['default_brand']);
        }

        if (isset($options['default_weight'])) {
            $config->setDefaultWeight($options['default_weight']);
        }

        if (isset($options['default_material'])) {
            $config->setDefaultMaterial($options['default_material']);
        }

        if (isset($options['return_policy_url'])) {
            $config->setReturnPolicyUrl($options['return_policy_url']);
        }

        if (isset($options['return_window_days'])) {
            $config->setReturnWindowDays($options['return_window_days']);
        }

        if (isset($options['privacy_policy_url'])) {
            $config->setPrivacyPolicyUrl($options['privacy_policy_url']);
        }

        if (isset($options['terms_of_service_url'])) {
            $config->setTermsOfServiceUrl($options['terms_of_service_url']);
        }

        return $config;
    }

    /**
     * Save ProductFeedConfig to database
     */
    private function saveProductFeedConfig(ProductFeedConfig $config): void
    {
        $this->productFeedConfigRepository->add($config);
        $this->objectManager->flush();

        $this->sharedStorage->set('product_feed_config', $config);
    }
}
