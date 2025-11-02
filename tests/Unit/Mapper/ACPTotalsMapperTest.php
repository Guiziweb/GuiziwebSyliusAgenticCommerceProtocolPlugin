<?php

declare(strict_types=1);

namespace Tests\Guiziweb\SyliusAgenticCommerceProtocolPlugin\Unit\Mapper;

use Doctrine\Common\Collections\ArrayCollection;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Mapper\ACPTotalsMapper;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;

/**
 * Tests for ACPTotalsMapper
 *
 * Validates correct mapping according to ACP spec:
 * - Total structure (openapi.agentic_checkout.yaml lines 348-367)
 * - Required fields: type, display_text, amount
 * - Type enum: items_base_amount, items_discount, subtotal, discount, fulfillment, tax, fee, total
 */
final class ACPTotalsMapperTest extends TestCase
{
    private ACPTotalsMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ACPTotalsMapper();
    }

    public function test_it_maps_all_required_totals_with_display_text(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);

        $order->method('getItemsTotal')->willReturn(3000); // $30.00
        $order->method('getTaxTotal')->willReturn(300); // $3.00
        $order->method('getTotal')->willReturn(3300); // $33.00
        $order->method('getAdjustments')->willReturn(new ArrayCollection([]));

        // When
        $result = $this->mapper->map($order);

        // Then - Verify structure
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(3, count($result)); // At least: items_base_amount, subtotal, total

        foreach ($result as $total) {
            // Required fields (spec lines 348-367)
            $this->assertArrayHasKey('type', $total);
            $this->assertArrayHasKey('display_text', $total);
            $this->assertArrayHasKey('amount', $total);

            // Verify display_text is present and not empty
            $this->assertIsString($total['display_text']);
            $this->assertNotEmpty($total['display_text']);

            // Verify amount is integer
            $this->assertIsInt($total['amount']);
        }
    }

    public function test_it_includes_items_base_amount(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $order->method('getItemsTotal')->willReturn(5000);
        $order->method('getTaxTotal')->willReturn(0);
        $order->method('getTotal')->willReturn(5000);
        $order->method('getAdjustments')->willReturn(new ArrayCollection([]));

        // When
        $result = $this->mapper->map($order);

        // Then
        $itemsBase = $this->findTotalByType($result, 'items_base_amount');
        $this->assertNotNull($itemsBase);
        $this->assertSame('items_base_amount', $itemsBase['type']);
        $this->assertSame('Items', $itemsBase['display_text']);
        $this->assertSame(5000, $itemsBase['amount']);
    }

    public function test_it_includes_subtotal(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $order->method('getItemsTotal')->willReturn(3000);
        $order->method('getTaxTotal')->willReturn(300);
        $order->method('getTotal')->willReturn(3300);
        $order->method('getAdjustments')->willReturn(new ArrayCollection([]));

        // When
        $result = $this->mapper->map($order);

        // Then
        $subtotal = $this->findTotalByType($result, 'subtotal');
        $this->assertNotNull($subtotal);
        $this->assertSame('subtotal', $subtotal['type']);
        $this->assertSame('Subtotal', $subtotal['display_text']);
        $this->assertSame(3000, $subtotal['amount']); // items - items_discount
    }

    public function test_it_includes_total(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $order->method('getItemsTotal')->willReturn(3000);
        $order->method('getTaxTotal')->willReturn(300);
        $order->method('getTotal')->willReturn(3300);
        $order->method('getAdjustments')->willReturn(new ArrayCollection([]));

        // When
        $result = $this->mapper->map($order);

        // Then
        $total = $this->findTotalByType($result, 'total');
        $this->assertNotNull($total);
        $this->assertSame('total', $total['type']);
        $this->assertSame('Total', $total['display_text']);
        $this->assertSame(3300, $total['amount']);
    }

    public function test_it_includes_items_discount_when_present(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $itemPromoAdj = $this->createMock(AdjustmentInterface::class);

        $order->method('getItemsTotal')->willReturn(3000);
        $order->method('getTaxTotal')->willReturn(300);
        $order->method('getTotal')->willReturn(3100);

        // Item-level promotion: -$2.00
        $itemPromoAdj->method('getType')->willReturn(AdjustmentInterface::ORDER_ITEM_PROMOTION_ADJUSTMENT);
        $itemPromoAdj->method('getAmount')->willReturn(-200);

        $order->method('getAdjustments')->willReturn(new ArrayCollection([$itemPromoAdj]));

        // When
        $result = $this->mapper->map($order);

        // Then
        $itemsDiscount = $this->findTotalByType($result, 'items_discount');
        $this->assertNotNull($itemsDiscount);
        $this->assertSame('items_discount', $itemsDiscount['type']);
        $this->assertSame('Item Discounts', $itemsDiscount['display_text']);
        $this->assertSame(-200, $itemsDiscount['amount']); // Negative!
    }

    public function test_it_includes_order_discount_when_present(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $orderPromoAdj = $this->createMock(AdjustmentInterface::class);

        $order->method('getItemsTotal')->willReturn(3000);
        $order->method('getTaxTotal')->willReturn(300);
        $order->method('getTotal')->willReturn(2800);

        // Order-level promotion: -$5.00
        $orderPromoAdj->method('getType')->willReturn(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT);
        $orderPromoAdj->method('getAmount')->willReturn(-500);

        $order->method('getAdjustments')->willReturn(new ArrayCollection([$orderPromoAdj]));

        // When
        $result = $this->mapper->map($order);

        // Then
        $discount = $this->findTotalByType($result, 'discount');
        $this->assertNotNull($discount);
        $this->assertSame('discount', $discount['type']);
        $this->assertSame('Discount', $discount['display_text']);
        $this->assertSame(-500, $discount['amount']); // Negative!
    }

    public function test_it_includes_fulfillment_when_present(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $shippingAdj = $this->createMock(AdjustmentInterface::class);

        $order->method('getItemsTotal')->willReturn(3000);
        $order->method('getTaxTotal')->willReturn(300);
        $order->method('getTotal')->willReturn(4300);

        // Shipping: $10.00
        $shippingAdj->method('getType')->willReturn(AdjustmentInterface::SHIPPING_ADJUSTMENT);
        $shippingAdj->method('getAmount')->willReturn(1000);

        $order->method('getAdjustments')->willReturn(new ArrayCollection([$shippingAdj]));

        // When
        $result = $this->mapper->map($order);

        // Then
        $fulfillment = $this->findTotalByType($result, 'fulfillment');
        $this->assertNotNull($fulfillment);
        $this->assertSame('fulfillment', $fulfillment['type']);
        $this->assertSame('Shipping', $fulfillment['display_text']);
        $this->assertSame(1000, $fulfillment['amount']);
    }

    public function test_it_includes_tax_when_present(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);

        $order->method('getItemsTotal')->willReturn(3000);
        $order->method('getTaxTotal')->willReturn(300); // $3.00 tax
        $order->method('getTotal')->willReturn(3300);
        $order->method('getAdjustments')->willReturn(new ArrayCollection([]));

        // When
        $result = $this->mapper->map($order);

        // Then
        $tax = $this->findTotalByType($result, 'tax');
        $this->assertNotNull($tax);
        $this->assertSame('tax', $tax['type']);
        $this->assertSame('Tax', $tax['display_text']);
        $this->assertSame(300, $tax['amount']);
    }

    public function test_it_omits_zero_discounts(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $order->method('getItemsTotal')->willReturn(3000);
        $order->method('getTaxTotal')->willReturn(0);
        $order->method('getTotal')->willReturn(3000);
        $order->method('getAdjustments')->willReturn(new ArrayCollection([]));

        // When
        $result = $this->mapper->map($order);

        // Then - No discount lines should be present
        $itemsDiscount = $this->findTotalByType($result, 'items_discount');
        $discount = $this->findTotalByType($result, 'discount');

        $this->assertNull($itemsDiscount);
        $this->assertNull($discount);
    }

    public function test_it_omits_zero_fulfillment(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $order->method('getItemsTotal')->willReturn(3000);
        $order->method('getTaxTotal')->willReturn(0);
        $order->method('getTotal')->willReturn(3000);
        $order->method('getAdjustments')->willReturn(new ArrayCollection([]));

        // When
        $result = $this->mapper->map($order);

        // Then - No fulfillment line should be present
        $fulfillment = $this->findTotalByType($result, 'fulfillment');
        $this->assertNull($fulfillment);
    }

    public function test_it_omits_zero_tax(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $order->method('getItemsTotal')->willReturn(3000);
        $order->method('getTaxTotal')->willReturn(0); // No tax
        $order->method('getTotal')->willReturn(3000);
        $order->method('getAdjustments')->willReturn(new ArrayCollection([]));

        // When
        $result = $this->mapper->map($order);

        // Then - No tax line should be present
        $tax = $this->findTotalByType($result, 'tax');
        $this->assertNull($tax);
    }

    public function test_it_handles_complete_order_with_all_totals(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $itemPromoAdj = $this->createMock(AdjustmentInterface::class);
        $orderPromoAdj = $this->createMock(AdjustmentInterface::class);
        $shippingAdj = $this->createMock(AdjustmentInterface::class);

        $order->method('getItemsTotal')->willReturn(10000); // $100.00
        $order->method('getTaxTotal')->willReturn(1000); // $10.00
        $order->method('getTotal')->willReturn(10100); // $101.00

        // Item discount: -$5.00
        $itemPromoAdj->method('getType')->willReturn(AdjustmentInterface::ORDER_ITEM_PROMOTION_ADJUSTMENT);
        $itemPromoAdj->method('getAmount')->willReturn(-500);

        // Order discount: -$10.00
        $orderPromoAdj->method('getType')->willReturn(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT);
        $orderPromoAdj->method('getAmount')->willReturn(-1000);

        // Shipping: $6.00
        $shippingAdj->method('getType')->willReturn(AdjustmentInterface::SHIPPING_ADJUSTMENT);
        $shippingAdj->method('getAmount')->willReturn(600);

        $order->method('getAdjustments')->willReturn(new ArrayCollection([
            $itemPromoAdj,
            $orderPromoAdj,
            $shippingAdj,
        ]));

        // When
        $result = $this->mapper->map($order);

        // Then - Should have all 7 types
        $this->assertCount(7, $result);

        $types = array_column($result, 'type');
        $this->assertContains('items_base_amount', $types);
        $this->assertContains('items_discount', $types);
        $this->assertContains('subtotal', $types);
        $this->assertContains('discount', $types);
        $this->assertContains('fulfillment', $types);
        $this->assertContains('tax', $types);
        $this->assertContains('total', $types);

        // Verify amounts
        $this->assertSame(10000, $this->findTotalByType($result, 'items_base_amount')['amount']);
        $this->assertSame(-500, $this->findTotalByType($result, 'items_discount')['amount']);
        $this->assertSame(9500, $this->findTotalByType($result, 'subtotal')['amount']); // 10000 - 500
        $this->assertSame(-1000, $this->findTotalByType($result, 'discount')['amount']);
        $this->assertSame(600, $this->findTotalByType($result, 'fulfillment')['amount']);
        $this->assertSame(1000, $this->findTotalByType($result, 'tax')['amount']);
        $this->assertSame(10100, $this->findTotalByType($result, 'total')['amount']);
    }

    public function test_it_handles_multiple_adjustments_of_same_type(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $promo1 = $this->createMock(AdjustmentInterface::class);
        $promo2 = $this->createMock(AdjustmentInterface::class);

        $order->method('getItemsTotal')->willReturn(5000);
        $order->method('getTaxTotal')->willReturn(0);
        $order->method('getTotal')->willReturn(4200);

        // Two item-level promotions
        $promo1->method('getType')->willReturn(AdjustmentInterface::ORDER_ITEM_PROMOTION_ADJUSTMENT);
        $promo1->method('getAmount')->willReturn(-300);

        $promo2->method('getType')->willReturn(AdjustmentInterface::ORDER_ITEM_PROMOTION_ADJUSTMENT);
        $promo2->method('getAmount')->willReturn(-500);

        $order->method('getAdjustments')->willReturn(new ArrayCollection([$promo1, $promo2]));

        // When
        $result = $this->mapper->map($order);

        // Then - Should sum both discounts
        $itemsDiscount = $this->findTotalByType($result, 'items_discount');
        $this->assertSame(-800, $itemsDiscount['amount']); // -300 + -500
    }

    /**
     * Helper to find a total by type
     *
     * @param array<int, array<string, mixed>> $totals
     * @param string $type
     * @return array<string, mixed>|null
     */
    private function findTotalByType(array $totals, string $type): ?array
    {
        foreach ($totals as $total) {
            if ($total['type'] === $type) {
                return $total;
            }
        }

        return null;
    }
}