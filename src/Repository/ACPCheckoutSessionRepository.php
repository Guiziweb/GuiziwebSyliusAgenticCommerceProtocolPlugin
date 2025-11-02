<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Entity\ACPCheckoutSession;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Enum\ACPStatus;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\ResourceRepositoryTrait;

/**
 * Repository for ACP Checkout Sessions
 *
 * @extends ServiceEntityRepository<ACPCheckoutSession>
 */
class ACPCheckoutSessionRepository extends ServiceEntityRepository implements ACPCheckoutSessionRepositoryInterface
{
    use ResourceRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ACPCheckoutSession::class);
    }

    /**
     * Find session by ACP ID
     *
     * @param string $acpId The ACP session ID (e.g., "checkout_session_abc123")
     */
    public function findOneByAcpId(string $acpId): ?ACPCheckoutSession
    {
        return $this->findOneBy(['acpId' => $acpId]);
    }

    /**
     * Find session by idempotency key
     *
     * Per RFC: Idempotency-Key is used to deduplicate create and complete requests
     *
     * @param string $idempotencyKey The idempotency key from request header
     */
    public function findOneByIdempotencyKey(string $idempotencyKey): ?ACPCheckoutSession
    {
        return $this->findOneBy(['idempotencyKey' => $idempotencyKey]);
    }

    /**
     * Find active sessions (not completed or canceled)
     *
     * @return ACPCheckoutSession[]
     */
    public function findActiveSessions(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.status NOT IN (:completedStatuses)')
            ->setParameter('completedStatuses', [ACPStatus::COMPLETED->value, ACPStatus::CANCELED->value])
            ->orderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find sessions by status
     *
     * @param string $status ACP status (not_ready_for_payment|ready_for_payment|completed|canceled|in_progress)
     *
     * @return ACPCheckoutSession[]
     */
    public function findByStatus(string $status): array
    {
        return $this->findBy(['status' => $status], ['id' => 'DESC']);
    }

    /**
     * Create query builder for listing sessions
     * Useful for admin grids
     */
    public function createListQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.order', 'o')
            ->leftJoin('s.channel', 'c')
            ->leftJoin('s.acpConfiguration', 'config')
            ->addSelect('o')
            ->addSelect('c')
            ->addSelect('config');
    }
}
