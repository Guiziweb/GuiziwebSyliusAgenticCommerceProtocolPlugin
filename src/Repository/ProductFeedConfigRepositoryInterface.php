<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Repository;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Entity\ProductFeedConfig;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * @extends RepositoryInterface<ProductFeedConfig>
 */
interface ProductFeedConfigRepositoryInterface extends RepositoryInterface
{
    /**
     * Find Product Feed configuration by Channel
     * One configuration per channel
     */
    public function findOneByChannel(ChannelInterface $channel): ?ProductFeedConfig;
}
