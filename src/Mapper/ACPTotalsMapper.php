<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Mapper;

use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;

/**
 * Maps Sylius totals to ACP format
 *
 * ACP Total format (spec lines 348-367):
 * - type: string (enum: items_base_amount, items_discount, subtotal, discount, fulfillment, tax, fee, total)
 * - display_text: string (REQUIRED - human readable label)
 * - amount: integer (in cents)
 *
 * Mapping:
 * - items_base_amount: Total items before discount
 * - items_discount: Item-level discounts (ORDER_*_PROMOTION)
 * - subtotal: items_base_amount + items_discount
 * - discount: Order-level discount (ORDER_PROMOTION)
 * - fulfillment: Shipping cost (SHIPPING_ADJUSTMENT)
 * - tax: Taxes (TAX_ADJUSTMENT)
 * - total: Grand total
 *
 * ACP Spec: openapi.agentic_checkout.yaml lines 348-367
 */
final readonly class ACPTotalsMapper implements ACPTotalsMapperInterface
{
    /**
     * Maps Sylius order totals to ACP format
     *
     * @param OrderInterface $order Sylius order
     *
     * @return array<int, array{type: string, display_text: string, amount: int}> ACP totals
     */
    public function map(OrderInterface $order): array
    {
        $totals = [];

        // 1. Items base amount
        $itemsBaseAmount = $order->getItemsTotal();
        $totals[] = [
            'type' => 'items_base_amount',
            'display_text' => 'Items',
            'amount' => $itemsBaseAmount,
        ];

        // 2. Items discount (item-level promotions)
        $itemsDiscount = $this->calculateItemsDiscount($order);
        if ($itemsDiscount > 0) {
            $totals[] = [
                'type' => 'items_discount',
                'display_text' => 'Item Discounts',
                'amount' => -$itemsDiscount, // Negative as it's a discount
            ];
        }

        // 3. Subtotal (items + items_discount)
        $subtotal = $itemsBaseAmount - $itemsDiscount;
        $totals[] = [
            'type' => 'subtotal',
            'display_text' => 'Subtotal',
            'amount' => $subtotal,
        ];

        // 4. Order-level discount
        $orderDiscount = $this->calculateOrderDiscount($order);
        if ($orderDiscount > 0) {
            $totals[] = [
                'type' => 'discount',
                'display_text' => 'Discount',
                'amount' => -$orderDiscount, // Negative as it's a discount
            ];
        }

        // 5. Fulfillment (shipping)
        $fulfillmentAmount = $this->calculateFulfillmentAmount($order);
        if ($fulfillmentAmount > 0) {
            $totals[] = [
                'type' => 'fulfillment',
                'display_text' => 'Shipping',
                'amount' => $fulfillmentAmount,
            ];
        }

        // 6. Tax
        $taxAmount = $order->getTaxTotal();
        if ($taxAmount > 0) {
            $totals[] = [
                'type' => 'tax',
                'display_text' => 'Tax',
                'amount' => $taxAmount,
            ];
        }

        // 7. Grand total
        $totals[] = [
            'type' => 'total',
            'display_text' => 'Total',
            'amount' => $order->getTotal(),
        ];

        return $totals;
    }

    /**
     * Calculates total item-level discounts
     *
     * @return int Total item discounts (absolute value)
     */
    private function calculateItemsDiscount(OrderInterface $order): int
    {
        $total = 0;

        // Loop through all item-level promotion adjustments
        foreach ($order->getAdjustments() as $adjustment) {
            if (in_array($adjustment->getType(), [
                AdjustmentInterface::ORDER_ITEM_PROMOTION_ADJUSTMENT,
                AdjustmentInterface::ORDER_UNIT_PROMOTION_ADJUSTMENT,
            ], true)) {
                // Promotion adjustments are negative in Sylius
                $total += abs($adjustment->getAmount());
            }
        }

        // Check item-level adjustments as well
        foreach ($order->getItems() as $item) {
            foreach ($item->getAdjustments() as $adjustment) {
                if (in_array($adjustment->getType(), [
                    AdjustmentInterface::ORDER_ITEM_PROMOTION_ADJUSTMENT,
                    AdjustmentInterface::ORDER_UNIT_PROMOTION_ADJUSTMENT,
                ], true)) {
                    $total += abs($adjustment->getAmount());
                }
            }
        }

        return $total;
    }

    /**
     * Calculates total order-level discounts
     *
     * @return int Total order discounts (absolute value)
     */
    private function calculateOrderDiscount(OrderInterface $order): int
    {
        $total = 0;

        foreach ($order->getAdjustments() as $adjustment) {
            if ($adjustment->getType() === AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT) {
                // Promotion adjustments are negative in Sylius
                $total += abs($adjustment->getAmount());
            }
        }

        return $total;
    }

    /**
     * Calculates total shipping cost
     *
     * @return int Total shipping amount
     */
    private function calculateFulfillmentAmount(OrderInterface $order): int
    {
        $total = 0;

        foreach ($order->getAdjustments() as $adjustment) {
            if ($adjustment->getType() === AdjustmentInterface::SHIPPING_ADJUSTMENT) {
                $total += $adjustment->getAmount();
            }
        }

        // Subtract shipping promotions
        foreach ($order->getAdjustments() as $adjustment) {
            if ($adjustment->getType() === AdjustmentInterface::ORDER_SHIPPING_PROMOTION_ADJUSTMENT) {
                $total += $adjustment->getAmount(); // Already negative
            }
        }

        return max(0, $total);
    }
}
