<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Mapper;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Entity\ProductFeedConfig;
use Sylius\Component\Core\Model\ChannelInterface;

/**
 * Maps Sylius products to OpenAI Product Feed format
 *
 * @see https://developers.openai.com/commerce/specs/feed/
 */
interface ProductFeedMapperInterface
{
    /**
     * Map product variants to OpenAI feed format
     *
     * @param array $variants Array of ProductVariantInterface
     *
     * @return array<array<string, mixed>>
     */
    public function mapVariants(
        array $variants,
        ChannelInterface $channel,
        ?ProductFeedConfig $config,
    ): array;
}
