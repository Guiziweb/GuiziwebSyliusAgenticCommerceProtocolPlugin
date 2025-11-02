<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Event;

/**
 * Event dispatched when an ACP checkout session is completed
 *
 * Pattern: Sylius\Bundle\ApiBundle\Event\OrderCompleted
 */
final class ACPOrderCompleted
{
    public function __construct(
        private string $acpCheckoutSessionId,
        private string $orderToken,
    ) {
    }

    public function acpCheckoutSessionId(): string
    {
        return $this->acpCheckoutSessionId;
    }

    public function orderToken(): string
    {
        return $this->orderToken;
    }
}
