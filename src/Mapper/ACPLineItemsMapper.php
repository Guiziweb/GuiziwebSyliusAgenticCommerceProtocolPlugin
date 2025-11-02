<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Mapper;

use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

/**
 * Maps Sylius OrderItems to ACP line_items format
 *
 * ACP LineItem format (spec lines 335-346):
 * - id: Unique line item ID (string)
 * - item: { id: variant_code, quantity: integer } (NESTED)
 * - base_amount: Total base price for line (in cents, integer)
 * - discount: Total discount for line (in cents, integer)
 * - subtotal: base_amount + discount (in cents, integer)
 * - tax: Total tax for line (in cents, integer)
 * - total: Total incl tax for line (in cents, integer)
 *
 * NOTE: Fields like name, description, image_url do NOT exist in ACP spec!
 *
 * ACP Spec: openapi.agentic_checkout.yaml lines 335-346
 */
final readonly class ACPLineItemsMapper implements ACPLineItemsMapperInterface
{
    /**
     * Maps Sylius order items to ACP format
     *
     * @param OrderInterface $order Sylius order
     *
     * @return array<int, array<string, mixed>> ACP line items
     */
    public function map(OrderInterface $order): array
    {
        $lineItems = [];

        foreach ($order->getItems() as $item) {
            $lineItems[] = $this->mapItem($item);
        }

        return $lineItems;
    }

    /**
     * Maps a Sylius OrderItem to ACP format
     *
     * @param OrderItemInterface $item Sylius order item
     *
     * @return array<string, mixed> ACP line item
     */
    private function mapItem(OrderItemInterface $item): array
    {
        $variant = $item->getVariant();
        $quantity = $item->getQuantity();

        // Generate unique line item ID
        // Format: line_item_{order_item_id}
        $lineItemId = 'line_item_' . $item->getId();

        // Nested item structure (ACP spec)
        $itemData = [
            'id' => $this->getVariantCode($variant),
            'quantity' => $quantity,
        ];

        // Amount calculations (for the ENTIRE line, not per unit!)
        $baseAmount = $item->getUnitPrice() * $quantity;
        $discount = $this->calculateItemDiscount($item);
        $subtotal = $baseAmount + $discount; // discount is negative
        $tax = $this->calculateItemTax($item);
        $total = $item->getTotal();

        $lineItem = [
            'id' => $lineItemId,
            'item' => $itemData,
            'base_amount' => $baseAmount,
            'discount' => $discount,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
        ];

        return $lineItem;
    }

    /**
     * Calculates total discount for an item
     *
     * @return int Total discount amount (negative if there is a discount)
     */
    private function calculateItemDiscount(OrderItemInterface $item): int
    {
        $total = 0;

        foreach ($item->getAdjustments() as $adjustment) {
            if (in_array($adjustment->getType(), [
                AdjustmentInterface::ORDER_ITEM_PROMOTION_ADJUSTMENT,
                AdjustmentInterface::ORDER_UNIT_PROMOTION_ADJUSTMENT,
            ], true)) {
                // Promotion adjustments are already negative in Sylius
                // Return as-is (negative value)
                $total += $adjustment->getAmount();
            }
        }

        return $total;
    }

    /**
     * Calculates taxes for an item
     *
     * @return int Total tax amount
     */
    private function calculateItemTax(OrderItemInterface $item): int
    {
        $total = 0;

        foreach ($item->getAdjustments() as $adjustment) {
            if ($adjustment->getType() === AdjustmentInterface::TAX_ADJUSTMENT) {
                $total += $adjustment->getAmount();
            }
        }

        // Check unit-level adjustments as well
        foreach ($item->getUnits() as $unit) {
            foreach ($unit->getAdjustments() as $adjustment) {
                if ($adjustment->getType() === AdjustmentInterface::TAX_ADJUSTMENT) {
                    $total += $adjustment->getAmount();
                }
            }
        }

        return $total;
    }

    /**
     * Gets the variant code (SKU)
     *
     * @return string Variant code or 'unknown'
     */
    private function getVariantCode(?ProductVariantInterface $variant): string
    {
        if ($variant === null) {
            return 'unknown';
        }

        return $variant->getCode() ?? 'variant_' . $variant->getId();
    }
}
