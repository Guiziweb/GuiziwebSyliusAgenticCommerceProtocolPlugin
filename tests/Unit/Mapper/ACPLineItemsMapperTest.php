<?php

declare(strict_types=1);

namespace Tests\Guiziweb\SyliusAgenticCommerceProtocolPlugin\Unit\Mapper;

use Doctrine\Common\Collections\ArrayCollection;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Mapper\ACPLineItemsMapper;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

/**
 * Tests for ACPLineItemsMapper
 *
 * Validates correct mapping according to ACP spec:
 * - LineItem structure (openapi.agentic_checkout.yaml lines 335-346)
 * - Nested item: {id, quantity}
 * - Fields: id, item, base_amount, discount, subtotal, tax, total (all integers)
 * - NO name, description, image_url fields (they don't exist in spec!)
 */
final class ACPLineItemsMapperTest extends TestCase
{
    private ACPLineItemsMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ACPLineItemsMapper();
    }

    public function test_it_maps_line_items_with_correct_nested_structure(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $orderItem = $this->createMock(OrderItemInterface::class);
        $variant = $this->createMock(ProductVariantInterface::class);

        $order->method('getItems')->willReturn(new ArrayCollection([$orderItem]));

        // OrderItem properties
        $orderItem->method('getId')->willReturn(123);
        $orderItem->method('getVariant')->willReturn($variant);
        $orderItem->method('getQuantity')->willReturn(2);
        $orderItem->method('getUnitPrice')->willReturn(1000); // $10.00 per unit
        $orderItem->method('getTotal')->willReturn(2000); // $20.00 total
        $orderItem->method('getAdjustments')->willReturn(new ArrayCollection([]));
        $orderItem->method('getUnits')->willReturn(new ArrayCollection([]));

        $variant->method('getCode')->willReturn('SKU123');

        // When
        $result = $this->mapper->map($order);

        // Then - Verify ACP spec compliance
        $this->assertIsArray($result);
        $this->assertCount(1, $result);

        $lineItem = $result[0];

        // Required fields according to spec (lines 335-346)
        $this->assertArrayHasKey('id', $lineItem);
        $this->assertArrayHasKey('item', $lineItem);
        $this->assertArrayHasKey('base_amount', $lineItem);
        $this->assertArrayHasKey('discount', $lineItem);
        $this->assertArrayHasKey('subtotal', $lineItem);
        $this->assertArrayHasKey('tax', $lineItem);
        $this->assertArrayHasKey('total', $lineItem);

        // Verify nested item structure
        $this->assertIsArray($lineItem['item']);
        $this->assertArrayHasKey('id', $lineItem['item']);
        $this->assertArrayHasKey('quantity', $lineItem['item']);
        $this->assertSame('SKU123', $lineItem['item']['id']);
        $this->assertSame(2, $lineItem['item']['quantity']);

        // Verify line item ID format
        $this->assertSame('line_item_123', $lineItem['id']);

        // Verify amounts (all integers, in cents)
        $this->assertIsInt($lineItem['base_amount']);
        $this->assertIsInt($lineItem['discount']);
        $this->assertIsInt($lineItem['subtotal']);
        $this->assertIsInt($lineItem['tax']);
        $this->assertIsInt($lineItem['total']);

        // Verify calculations (for entire line, not per unit!)
        $this->assertSame(2000, $lineItem['base_amount']); // 2 units × $10.00
        $this->assertSame(0, $lineItem['discount']);
        $this->assertSame(2000, $lineItem['subtotal']);
        $this->assertSame(0, $lineItem['tax']);
        $this->assertSame(2000, $lineItem['total']);
    }

    public function test_it_does_not_include_non_existent_fields(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $orderItem = $this->createMock(OrderItemInterface::class);
        $variant = $this->createMock(ProductVariantInterface::class);

        $order->method('getItems')->willReturn(new ArrayCollection([$orderItem]));
        $orderItem->method('getId')->willReturn(1);
        $orderItem->method('getVariant')->willReturn($variant);
        $orderItem->method('getQuantity')->willReturn(1);
        $orderItem->method('getUnitPrice')->willReturn(1000);
        $orderItem->method('getTotal')->willReturn(1000);
        $orderItem->method('getAdjustments')->willReturn(new ArrayCollection([]));
        $orderItem->method('getUnits')->willReturn(new ArrayCollection([]));
        $variant->method('getCode')->willReturn('TEST');

        // When
        $result = $this->mapper->map($order);

        // Then - These fields should NOT exist (spec lines 335-346)
        $lineItem = $result[0];
        $this->assertArrayNotHasKey('name', $lineItem);
        $this->assertArrayNotHasKey('description', $lineItem);
        $this->assertArrayNotHasKey('image_url', $lineItem);
    }

    public function test_it_calculates_item_level_discount_as_negative(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $orderItem = $this->createMock(OrderItemInterface::class);
        $variant = $this->createMock(ProductVariantInterface::class);
        $adjustment = $this->createMock(AdjustmentInterface::class);

        $order->method('getItems')->willReturn(new ArrayCollection([$orderItem]));
        $orderItem->method('getId')->willReturn(1);
        $orderItem->method('getVariant')->willReturn($variant);
        $orderItem->method('getQuantity')->willReturn(2);
        $orderItem->method('getUnitPrice')->willReturn(1000);
        $orderItem->method('getTotal')->willReturn(1800); // After discount

        // Item-level promotion: -$2.00
        $adjustment->method('getType')->willReturn(AdjustmentInterface::ORDER_ITEM_PROMOTION_ADJUSTMENT);
        $adjustment->method('getAmount')->willReturn(-200); // Sylius stores as negative

        $orderItem->method('getAdjustments')->willReturn(new ArrayCollection([$adjustment]));
        $orderItem->method('getUnits')->willReturn(new ArrayCollection([]));
        $variant->method('getCode')->willReturn('TEST');

        // When
        $result = $this->mapper->map($order);

        // Then
        $lineItem = $result[0];

        // CRITICAL: discount must be negative (spec line 340)
        $this->assertSame(-200, $lineItem['discount']);

        // Verify calculation: base_amount + discount = subtotal
        $this->assertSame(2000, $lineItem['base_amount']); // 2 × $10.00
        $this->assertSame(-200, $lineItem['discount']); // -$2.00
        $this->assertSame(1800, $lineItem['subtotal']); // $18.00
    }

    public function test_it_calculates_tax_correctly(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $orderItem = $this->createMock(OrderItemInterface::class);
        $variant = $this->createMock(ProductVariantInterface::class);
        $taxAdjustment = $this->createMock(AdjustmentInterface::class);

        $order->method('getItems')->willReturn(new ArrayCollection([$orderItem]));
        $orderItem->method('getId')->willReturn(1);
        $orderItem->method('getVariant')->willReturn($variant);
        $orderItem->method('getQuantity')->willReturn(1);
        $orderItem->method('getUnitPrice')->willReturn(1000);
        $orderItem->method('getTotal')->willReturn(1100); // Including tax

        // Tax: $1.00
        $taxAdjustment->method('getType')->willReturn(AdjustmentInterface::TAX_ADJUSTMENT);
        $taxAdjustment->method('getAmount')->willReturn(100);

        $orderItem->method('getAdjustments')->willReturn(new ArrayCollection([$taxAdjustment]));
        $orderItem->method('getUnits')->willReturn(new ArrayCollection([]));
        $variant->method('getCode')->willReturn('TEST');

        // When
        $result = $this->mapper->map($order);

        // Then
        $lineItem = $result[0];
        $this->assertSame(100, $lineItem['tax']);
        $this->assertSame(1100, $lineItem['total']);
    }

    public function test_it_includes_unit_level_tax(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $orderItem = $this->createMock(OrderItemInterface::class);
        $variant = $this->createMock(ProductVariantInterface::class);
        $unit1 = $this->createMock(OrderItemUnitInterface::class);
        $unit2 = $this->createMock(OrderItemUnitInterface::class);
        $taxAdj1 = $this->createMock(AdjustmentInterface::class);
        $taxAdj2 = $this->createMock(AdjustmentInterface::class);

        $order->method('getItems')->willReturn(new ArrayCollection([$orderItem]));
        $orderItem->method('getId')->willReturn(1);
        $orderItem->method('getVariant')->willReturn($variant);
        $orderItem->method('getQuantity')->willReturn(2);
        $orderItem->method('getUnitPrice')->willReturn(1000);
        $orderItem->method('getTotal')->willReturn(2200);
        $orderItem->method('getAdjustments')->willReturn(new ArrayCollection([]));

        // Unit-level taxes
        $taxAdj1->method('getType')->willReturn(AdjustmentInterface::TAX_ADJUSTMENT);
        $taxAdj1->method('getAmount')->willReturn(100);

        $taxAdj2->method('getType')->willReturn(AdjustmentInterface::TAX_ADJUSTMENT);
        $taxAdj2->method('getAmount')->willReturn(100);

        $unit1->method('getAdjustments')->willReturn(new ArrayCollection([$taxAdj1]));
        $unit2->method('getAdjustments')->willReturn(new ArrayCollection([$taxAdj2]));

        $orderItem->method('getUnits')->willReturn(new ArrayCollection([$unit1, $unit2]));
        $variant->method('getCode')->willReturn('TEST');

        // When
        $result = $this->mapper->map($order);

        // Then - Should sum unit-level taxes
        $lineItem = $result[0];
        $this->assertSame(200, $lineItem['tax']); // 2 × $1.00
    }

    public function test_it_handles_multiple_line_items(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $orderItem1 = $this->createMock(OrderItemInterface::class);
        $orderItem2 = $this->createMock(OrderItemInterface::class);
        $variant1 = $this->createMock(ProductVariantInterface::class);
        $variant2 = $this->createMock(ProductVariantInterface::class);

        $order->method('getItems')->willReturn(new ArrayCollection([$orderItem1, $orderItem2]));

        // Item 1
        $orderItem1->method('getId')->willReturn(10);
        $orderItem1->method('getVariant')->willReturn($variant1);
        $orderItem1->method('getQuantity')->willReturn(1);
        $orderItem1->method('getUnitPrice')->willReturn(1000);
        $orderItem1->method('getTotal')->willReturn(1000);
        $orderItem1->method('getAdjustments')->willReturn(new ArrayCollection([]));
        $orderItem1->method('getUnits')->willReturn(new ArrayCollection([]));
        $variant1->method('getCode')->willReturn('SKU1');

        // Item 2
        $orderItem2->method('getId')->willReturn(20);
        $orderItem2->method('getVariant')->willReturn($variant2);
        $orderItem2->method('getQuantity')->willReturn(3);
        $orderItem2->method('getUnitPrice')->willReturn(500);
        $orderItem2->method('getTotal')->willReturn(1500);
        $orderItem2->method('getAdjustments')->willReturn(new ArrayCollection([]));
        $orderItem2->method('getUnits')->willReturn(new ArrayCollection([]));
        $variant2->method('getCode')->willReturn('SKU2');

        // When
        $result = $this->mapper->map($order);

        // Then
        $this->assertCount(2, $result);

        $this->assertSame('line_item_10', $result[0]['id']);
        $this->assertSame('SKU1', $result[0]['item']['id']);
        $this->assertSame(1, $result[0]['item']['quantity']);
        $this->assertSame(1000, $result[0]['base_amount']);

        $this->assertSame('line_item_20', $result[1]['id']);
        $this->assertSame('SKU2', $result[1]['item']['id']);
        $this->assertSame(3, $result[1]['item']['quantity']);
        $this->assertSame(1500, $result[1]['base_amount']); // 3 × $5.00
    }

    public function test_it_returns_empty_array_when_no_items(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $order->method('getItems')->willReturn(new ArrayCollection([]));

        // When
        $result = $this->mapper->map($order);

        // Then
        $this->assertSame([], $result);
    }

    public function test_it_handles_variant_without_code(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $orderItem = $this->createMock(OrderItemInterface::class);
        $variant = $this->createMock(ProductVariantInterface::class);

        $order->method('getItems')->willReturn(new ArrayCollection([$orderItem]));
        $orderItem->method('getId')->willReturn(1);
        $orderItem->method('getVariant')->willReturn($variant);
        $orderItem->method('getQuantity')->willReturn(1);
        $orderItem->method('getUnitPrice')->willReturn(1000);
        $orderItem->method('getTotal')->willReturn(1000);
        $orderItem->method('getAdjustments')->willReturn(new ArrayCollection([]));
        $orderItem->method('getUnits')->willReturn(new ArrayCollection([]));

        // Variant without code (fallback to ID)
        $variant->method('getCode')->willReturn(null);
        $variant->method('getId')->willReturn(456);

        // When
        $result = $this->mapper->map($order);

        // Then - Should use variant_ID as fallback
        $lineItem = $result[0];
        $this->assertSame('variant_456', $lineItem['item']['id']);
    }

    public function test_it_handles_null_variant(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $orderItem = $this->createMock(OrderItemInterface::class);

        $order->method('getItems')->willReturn(new ArrayCollection([$orderItem]));
        $orderItem->method('getId')->willReturn(1);
        $orderItem->method('getVariant')->willReturn(null);
        $orderItem->method('getQuantity')->willReturn(1);
        $orderItem->method('getUnitPrice')->willReturn(1000);
        $orderItem->method('getTotal')->willReturn(1000);
        $orderItem->method('getAdjustments')->willReturn(new ArrayCollection([]));
        $orderItem->method('getUnits')->willReturn(new ArrayCollection([]));

        // When
        $result = $this->mapper->map($order);

        // Then - Should use 'unknown' as fallback
        $lineItem = $result[0];
        $this->assertSame('unknown', $lineItem['item']['id']);
    }

    public function test_it_handles_complex_scenario_with_discount_and_tax(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $orderItem = $this->createMock(OrderItemInterface::class);
        $variant = $this->createMock(ProductVariantInterface::class);
        $discountAdj = $this->createMock(AdjustmentInterface::class);
        $taxAdj = $this->createMock(AdjustmentInterface::class);

        $order->method('getItems')->willReturn(new ArrayCollection([$orderItem]));
        $orderItem->method('getId')->willReturn(1);
        $orderItem->method('getVariant')->willReturn($variant);
        $orderItem->method('getQuantity')->willReturn(3);
        $orderItem->method('getUnitPrice')->willReturn(2000); // $20.00 per unit
        $orderItem->method('getTotal')->willReturn(6300); // After discount + tax

        // Discount: -$3.00
        $discountAdj->method('getType')->willReturn(AdjustmentInterface::ORDER_ITEM_PROMOTION_ADJUSTMENT);
        $discountAdj->method('getAmount')->willReturn(-300);

        // Tax: $6.00
        $taxAdj->method('getType')->willReturn(AdjustmentInterface::TAX_ADJUSTMENT);
        $taxAdj->method('getAmount')->willReturn(600);

        $orderItem->method('getAdjustments')->willReturn(new ArrayCollection([$discountAdj, $taxAdj]));
        $orderItem->method('getUnits')->willReturn(new ArrayCollection([]));
        $variant->method('getCode')->willReturn('COMPLEX');

        // When
        $result = $this->mapper->map($order);

        // Then - Verify full calculation chain
        $lineItem = $result[0];
        $this->assertSame(6000, $lineItem['base_amount']); // 3 × $20.00 = $60.00
        $this->assertSame(-300, $lineItem['discount']); // -$3.00
        $this->assertSame(5700, $lineItem['subtotal']); // $60.00 - $3.00 = $57.00
        $this->assertSame(600, $lineItem['tax']); // $6.00
        $this->assertSame(6300, $lineItem['total']); // $63.00
    }
}