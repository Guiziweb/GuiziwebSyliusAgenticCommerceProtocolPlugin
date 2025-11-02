<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Fixture;

use Sylius\Bundle\CoreBundle\Fixture\AbstractResourceFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * Fixture for loading ProductFeedConfig data
 */
final class ProductFeedConfigFixture extends AbstractResourceFixture
{
    public function getName(): string
    {
        return 'product_feed_config';
    }

    protected function configureResourceNode(ArrayNodeDefinition $resourceNode): void
    {
        // @phpstan-ignore-next-line
        $resourceNode
            ->children()
                ->scalarNode('channel')->cannotBeEmpty()->end()
                ->scalarNode('feed_endpoint')->end()
                ->scalarNode('feed_bearer_token')->end()
                ->scalarNode('default_brand')->end()
                ->scalarNode('default_weight')->end()
                ->scalarNode('default_material')->end()
                ->scalarNode('return_policy_url')->end()
                ->integerNode('return_window_days')->end()
                ->scalarNode('privacy_policy_url')->end()
                ->scalarNode('terms_of_service_url')->end()
        ;
    }
}
