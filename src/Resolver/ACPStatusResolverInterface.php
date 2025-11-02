<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Resolver;

use Sylius\Component\Core\Model\OrderInterface;

/**
 * Resolves ACP status from a Sylius Order
 *
 * Maps Sylius order state to ACP checkout session status
 */
interface ACPStatusResolverInterface
{
    /**
     * Resolves the ACP status of a Sylius order
     *
     * @param OrderInterface $order Sylius order
     *
     * @return string ACP status (not_ready_for_payment|ready_for_payment|in_progress|completed|canceled)
     */
    public function resolve(OrderInterface $order): string;
}
