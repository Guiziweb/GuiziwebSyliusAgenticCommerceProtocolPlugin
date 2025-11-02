<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Repository;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Entity\ACPCheckoutSession;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * @extends RepositoryInterface<ACPCheckoutSession>
 */
interface ACPCheckoutSessionRepositoryInterface extends RepositoryInterface
{
    /**
     * Find session by ACP ID
     */
    public function findOneByAcpId(string $acpId): ?ACPCheckoutSession;

    /**
     * Find session by idempotency key
     * Per ACP RFC Section 6: Used for idempotency deduplication on create/complete
     */
    public function findOneByIdempotencyKey(string $idempotencyKey): ?ACPCheckoutSession;
}
