<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Mapper;

use Sylius\Component\Core\Model\OrderInterface;

/**
 * Maps Sylius Order totals to ACP format
 *
 * Spec: openapi.agentic_checkout.yaml Total structure (lines 348-367)
 */
interface ACPTotalsMapperInterface
{
    /**
     * Maps Sylius order totals to ACP totals array
     *
     * @return array<int, array<string, mixed>> Array of ACP totals
     */
    public function map(OrderInterface $order): array;
}
