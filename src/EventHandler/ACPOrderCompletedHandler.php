<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\EventHandler;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Entity\ACPCheckoutSessionInterface;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Event\ACPOrderCompleted;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Repository\ACPCheckoutSessionRepositoryInterface;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Service\ACPWebhookNotifier;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles ACPOrderCompleted event by sending webhook to ChatGPT
 *
 * Pattern: Sylius\Bundle\ApiBundle\EventHandler\OrderCompletedHandler
 */
#[AsMessageHandler]
final readonly class ACPOrderCompletedHandler
{
    /**
     * @param OrderRepositoryInterface<OrderInterface> $orderRepository
     */
    public function __construct(
        private ACPCheckoutSessionRepositoryInterface $acpCheckoutSessionRepository,
        private OrderRepositoryInterface $orderRepository,
        private ACPWebhookNotifier $webhookNotifier,
    ) {
    }

    public function __invoke(ACPOrderCompleted $event): void
    {
        // Retrieve session
        $session = $this->acpCheckoutSessionRepository->findOneByAcpId($event->acpCheckoutSessionId());
        if (!$session instanceof ACPCheckoutSessionInterface) {
            // Session not found - skip (shouldn't happen in normal flow)
            return;
        }

        // Retrieve order
        $order = $this->orderRepository->findOneBy(['tokenValue' => $event->orderToken()]);
        if (!$order instanceof OrderInterface) {
            // Order not found - skip (shouldn't happen in normal flow)
            return;
        }

        // Send webhook notification to ChatGPT
        $this->webhookNotifier->notify($session, $order, 'order_create', 'created');
    }
}
