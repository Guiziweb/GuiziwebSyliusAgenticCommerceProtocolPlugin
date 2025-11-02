<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Mapper;

use Sylius\Component\Core\Model\OrderInterface;

/**
 * Maps Sylius OrderItems to ACP line_items format
 *
 * Spec: openapi.agentic_checkout.yaml LineItem structure (lines 320-347)
 */
interface ACPLineItemsMapperInterface
{
    /**
     * Maps Sylius order items to ACP line_items array
     *
     * @return array<int, array<string, mixed>> Array of ACP line items
     */
    public function map(OrderInterface $order): array;
}
