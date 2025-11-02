<?php

declare(strict_types=1);

namespace Tests\Guiziweb\SyliusAgenticCommerceProtocolPlugin\Unit\Mapper;

use Doctrine\Common\Collections\ArrayCollection;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Mapper\ACPFulfillmentMapper;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Registry\ServiceRegistryInterface;
use Sylius\Component\Shipping\Calculator\CalculatorInterface;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;

/**
 * Tests for ACPFulfillmentMapper
 *
 * Validates correct mapping according to ACP spec:
 * - FulfillmentOptionShipping structure (openapi.agentic_checkout.yaml lines 369-383)
 * - Fields: type, id, title, subtotal, tax, total (as strings)
 */
final class ACPFulfillmentMapperTest extends TestCase
{
    private ShippingMethodsResolverInterface $shippingMethodsResolver;
    private ServiceRegistryInterface $calculatorRegistry;
    private ACPFulfillmentMapper $mapper;

    protected function setUp(): void
    {
        $this->shippingMethodsResolver = $this->createMock(ShippingMethodsResolverInterface::class);
        $this->calculatorRegistry = $this->createMock(ServiceRegistryInterface::class);

        $this->mapper = new ACPFulfillmentMapper(
            $this->shippingMethodsResolver,
            $this->calculatorRegistry
        );
    }

    public function test_it_maps_fulfillment_options_with_correct_structure(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $shipment = $this->createMock(ShipmentInterface::class);
        $shippingMethod = $this->createMock(ShippingMethodInterface::class);
        $calculator = $this->createMock(CalculatorInterface::class);

        // Order has one shipment
        $order->method('getShipments')->willReturn(new ArrayCollection([$shipment]));

        // Resolver returns one shipping method
        $this->shippingMethodsResolver
            ->method('getSupportedMethods')
            ->with($shipment)
            ->willReturn([$shippingMethod]);

        // Shipping method properties
        $shippingMethod->method('getCode')->willReturn('standard_shipping');
        $shippingMethod->method('getName')->willReturn('Standard Shipping');
        $shippingMethod->method('getDescription')->willReturn('Delivery in 3-5 days');
        $shippingMethod->method('getCalculator')->willReturn('flat_rate');
        $shippingMethod->method('getConfiguration')->willReturn(['amount' => 1000]);

        // Calculator returns 1000 cents ($10.00)
        $this->calculatorRegistry
            ->method('get')
            ->with('flat_rate')
            ->willReturn($calculator);

        $calculator
            ->method('calculate')
            ->with($shipment, ['amount' => 1000])
            ->willReturn(1000);

        $order->method('getCurrencyCode')->willReturn('USD');

        // When
        $result = $this->mapper->mapOptions($order);

        // Then - Verify ACP spec compliance
        $this->assertIsArray($result);
        $this->assertCount(1, $result);

        $option = $result[0];

        // Required fields according to spec
        $this->assertArrayHasKey('type', $option);
        $this->assertArrayHasKey('id', $option);
        $this->assertArrayHasKey('title', $option);
        $this->assertArrayHasKey('subtotal', $option);
        $this->assertArrayHasKey('tax', $option);
        $this->assertArrayHasKey('total', $option);

        // Verify correct values
        $this->assertSame('shipping', $option['type']);
        $this->assertSame('standard_shipping', $option['id']);
        $this->assertSame('Standard Shipping', $option['title']);

        // CRITICAL: subtotal, tax, total must be STRINGS in decimal format
        $this->assertIsString($option['subtotal']);
        $this->assertIsString($option['tax']);
        $this->assertIsString($option['total']);

        $this->assertSame('10.00', $option['subtotal']); // 1000 cents = $10.00
        $this->assertSame('0.00', $option['tax']);
        $this->assertSame('10.00', $option['total']);

        // Optional subtitle (from description)
        $this->assertArrayHasKey('subtitle', $option);
        $this->assertSame('Delivery in 3-5 days', $option['subtitle']);
    }

    public function test_it_converts_cents_to_decimal_string_correctly(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $shipment = $this->createMock(ShipmentInterface::class);
        $shippingMethod = $this->createMock(ShippingMethodInterface::class);
        $calculator = $this->createMock(CalculatorInterface::class);

        $order->method('getShipments')->willReturn(new ArrayCollection([$shipment]));
        $this->shippingMethodsResolver->method('getSupportedMethods')->willReturn([$shippingMethod]);

        $shippingMethod->method('getCode')->willReturn('express');
        $shippingMethod->method('getName')->willReturn('Express');
        $shippingMethod->method('getCalculator')->willReturn('calculator');
        $shippingMethod->method('getConfiguration')->willReturn([]);

        $this->calculatorRegistry->method('get')->willReturn($calculator);

        // Test with 1550 cents = $15.50
        $calculator->method('calculate')->willReturn(1550);

        $order->method('getCurrencyCode')->willReturn('USD');

        // When
        $result = $this->mapper->mapOptions($order);

        // Then
        $option = $result[0];
        $this->assertSame('15.50', $option['subtotal']);
        $this->assertSame('15.50', $option['total']);
    }

    public function test_it_does_not_include_old_cost_and_currency_fields(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $shipment = $this->createMock(ShipmentInterface::class);
        $shippingMethod = $this->createMock(ShippingMethodInterface::class);
        $calculator = $this->createMock(CalculatorInterface::class);

        $order->method('getShipments')->willReturn(new ArrayCollection([$shipment]));
        $this->shippingMethodsResolver->method('getSupportedMethods')->willReturn([$shippingMethod]);

        $shippingMethod->method('getCode')->willReturn('test');
        $shippingMethod->method('getName')->willReturn('Test');
        $shippingMethod->method('getCalculator')->willReturn('calc');
        $shippingMethod->method('getConfiguration')->willReturn([]);

        $this->calculatorRegistry->method('get')->willReturn($calculator);
        $calculator->method('calculate')->willReturn(1000);
        $order->method('getCurrencyCode')->willReturn('USD');

        // When
        $result = $this->mapper->mapOptions($order);

        // Then - These fields should NOT exist (they were in old incorrect implementation)
        $option = $result[0];
        $this->assertArrayNotHasKey('cost', $option);
        $this->assertArrayNotHasKey('currency', $option);
        $this->assertArrayNotHasKey('label', $option); // Should be 'title', not 'label'
    }

    public function test_it_returns_empty_array_when_no_shipment(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $order->method('getShipments')->willReturn(new ArrayCollection([]));

        // When
        $result = $this->mapper->mapOptions($order);

        // Then
        $this->assertSame([], $result);
    }

    public function test_it_handles_calculator_exception_gracefully(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $shipment = $this->createMock(ShipmentInterface::class);
        $shippingMethod = $this->createMock(ShippingMethodInterface::class);

        $order->method('getShipments')->willReturn(new ArrayCollection([$shipment]));
        $this->shippingMethodsResolver->method('getSupportedMethods')->willReturn([$shippingMethod]);

        $shippingMethod->method('getCode')->willReturn('test');
        $shippingMethod->method('getName')->willReturn('Test');
        $shippingMethod->method('getCalculator')->willReturn('invalid_calculator');
        $shippingMethod->method('getConfiguration')->willReturn([]);

        // Calculator throws exception
        $this->calculatorRegistry
            ->method('get')
            ->willThrowException(new \InvalidArgumentException('Calculator not found'));

        $order->method('getCurrencyCode')->willReturn('USD');

        // When
        $result = $this->mapper->mapOptions($order);

        // Then - Should return option with 0 cost instead of failing
        $option = $result[0];
        $this->assertSame('0.00', $option['subtotal']);
        $this->assertSame('0.00', $option['total']);
    }

    public function test_it_maps_selected_shipping_method_id(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $shipment = $this->createMock(ShipmentInterface::class);
        $shippingMethod = $this->createMock(ShippingMethodInterface::class);

        $order->method('getShipments')->willReturn(new ArrayCollection([$shipment]));
        $shipment->method('getMethod')->willReturn($shippingMethod);
        $shippingMethod->method('getCode')->willReturn('express_shipping');

        // When
        $result = $this->mapper->mapSelectedOption($order);

        // Then
        $this->assertSame('express_shipping', $result);
    }

    public function test_it_returns_null_when_no_shipping_method_selected(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $shipment = $this->createMock(ShipmentInterface::class);

        $order->method('getShipments')->willReturn(new ArrayCollection([$shipment]));
        $shipment->method('getMethod')->willReturn(null);

        // When
        $result = $this->mapper->mapSelectedOption($order);

        // Then
        $this->assertNull($result);
    }

    public function test_it_omits_subtitle_when_no_description(): void
    {
        // Given
        $order = $this->createMock(OrderInterface::class);
        $shipment = $this->createMock(ShipmentInterface::class);
        $shippingMethod = $this->createMock(ShippingMethodInterface::class);
        $calculator = $this->createMock(CalculatorInterface::class);

        $order->method('getShipments')->willReturn(new ArrayCollection([$shipment]));
        $this->shippingMethodsResolver->method('getSupportedMethods')->willReturn([$shippingMethod]);

        $shippingMethod->method('getCode')->willReturn('test');
        $shippingMethod->method('getName')->willReturn('Test');
        $shippingMethod->method('getDescription')->willReturn(null); // No description
        $shippingMethod->method('getCalculator')->willReturn('calc');
        $shippingMethod->method('getConfiguration')->willReturn([]);

        $this->calculatorRegistry->method('get')->willReturn($calculator);
        $calculator->method('calculate')->willReturn(1000);
        $order->method('getCurrencyCode')->willReturn('USD');

        // When
        $result = $this->mapper->mapOptions($order);

        // Then - subtitle should not be present
        $option = $result[0];
        $this->assertArrayNotHasKey('subtitle', $option);
    }
}