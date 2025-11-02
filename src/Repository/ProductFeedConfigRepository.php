<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Entity\ProductFeedConfig;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\ResourceRepositoryTrait;
use Sylius\Component\Channel\Model\ChannelInterface;

/**
 * Repository for Product Feed Configuration
 *
 * @extends ServiceEntityRepository<ProductFeedConfig>
 */
class ProductFeedConfigRepository extends ServiceEntityRepository implements ProductFeedConfigRepositoryInterface
{
    use ResourceRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductFeedConfig::class);
    }

    /**
     * Find Product Feed configuration by Channel
     *
     * @param ChannelInterface $channel The channel to find configuration for
     */
    public function findOneByChannel(ChannelInterface $channel): ?ProductFeedConfig
    {
        return $this->findOneBy(['channel' => $channel]);
    }
}
