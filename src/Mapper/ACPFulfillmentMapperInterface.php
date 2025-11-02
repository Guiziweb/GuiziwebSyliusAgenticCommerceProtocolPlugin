<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Mapper;

use Sylius\Component\Core\Model\OrderInterface;

/**
 * Maps Sylius shipping methods to ACP fulfillment_options format
 *
 * Spec: openapi.agentic_checkout.yaml FulfillmentOptionShipping structure (lines 369-383)
 */
interface ACPFulfillmentMapperInterface
{
    /**
     * Maps available shipping methods to ACP fulfillment_options array
     *
     * @return array<int, array<string, mixed>> Array of ACP fulfillment options
     */
    public function mapOptions(OrderInterface $order): array;

    /**
     * Returns the ID of the selected shipping method, if any
     *
     * @return string|null Shipping method code, or null if not selected
     */
    public function mapSelectedOption(OrderInterface $order): ?string;
}
