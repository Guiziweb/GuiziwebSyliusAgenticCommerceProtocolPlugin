<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Mapper;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Registry\ServiceRegistryInterface;
use Sylius\Component\Shipping\Calculator\CalculatorInterface;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;

/**
 * Maps Sylius shipping methods to ACP fulfillment format
 *
 * ACP fulfillment_option format (FulfillmentOptionShipping):
 * - type: "shipping" | "digital"
 * - id: Unique identifier (shipping method code)
 * - title: Display name (REQUIRED)
 * - subtitle?: Optional subtitle
 * - carrier?: Carrier name
 * - earliest_delivery_time?: ISO 8601 datetime
 * - latest_delivery_time?: ISO 8601 datetime
 * - subtotal: Shipping cost before tax (string, e.g. "10.00")
 * - tax: Tax on shipping (string, e.g. "1.00")
 * - total: Total shipping cost (string, e.g. "11.00")
 *
 * ACP Spec: openapi.agentic_checkout.yaml lines 369-396
 */
final readonly class ACPFulfillmentMapper implements ACPFulfillmentMapperInterface
{
    public function __construct(
        private ShippingMethodsResolverInterface $shippingMethodsResolver,
        private ServiceRegistryInterface $calculatorRegistry,
    ) {
    }

    /**
     * Maps available shipping methods to ACP format
     *
     * @param OrderInterface $order Sylius order
     *
     * @return array<int, array<string, mixed>> ACP fulfillment options
     */
    public function mapOptions(OrderInterface $order): array
    {
        $options = [];

        // Get first shipment (Sylius supports multi-shipment but ACP assumes single)
        $shipment = $order->getShipments()->first();
        if ($shipment === false || !$shipment instanceof ShipmentInterface) {
            return [];
        }

        // Resolve available shipping methods
        try {
            $shippingMethods = $this->shippingMethodsResolver->getSupportedMethods($shipment);
        } catch (\Exception $e) {
            // If unable to resolve (e.g., no address), return empty
            return [];
        }

        foreach ($shippingMethods as $shippingMethod) {
            $options[] = $this->mapShippingMethod($shippingMethod, $order);
        }

        return $options;
    }

    /**
     * Gets the selected fulfillment option ID
     *
     * @param OrderInterface $order Sylius order
     *
     * @return string|null Selected shipping method ID or null
     */
    public function mapSelectedOption(OrderInterface $order): ?string
    {
        $shipment = $order->getShipments()->first();
        if ($shipment === false || !$shipment instanceof ShipmentInterface) {
            return null;
        }

        $method = $shipment->getMethod();
        if ($method === null) {
            return null;
        }

        return $this->getShippingMethodId($method);
    }

    /**
     * Maps a Sylius ShippingMethod to ACP format
     *
     * @param \Sylius\Component\Shipping\Model\ShippingMethodInterface $method Sylius shipping method
     * @param OrderInterface $order Order for cost calculation
     *
     * @return array<string, mixed> ACP fulfillment option
     */
    private function mapShippingMethod(\Sylius\Component\Shipping\Model\ShippingMethodInterface $method, OrderInterface $order): array
    {
        // Calculate shipping cost in cents
        $costInCents = $this->calculateShippingCost($method, $order);

        // Convert to decimal string format (e.g., 1500 cents -> "15.00")
        $subtotalString = number_format($costInCents / 100, 2, '.', '');

        // For now, shipping has no tax (could be calculated from adjustments if needed)
        $taxString = '0.00';
        $totalString = $subtotalString;

        $option = [
            'type' => 'shipping',
            'id' => $this->getShippingMethodId($method),
            'title' => $method->getName() ?? 'Unknown',
            'subtotal' => $subtotalString,
            'tax' => $taxString,
            'total' => $totalString,
        ];

        // Optional subtitle (description)
        $description = $method->getDescription();
        if ($description !== null && $description !== '') {
            $option['subtitle'] = $description;
        }

        return $option;
    }

    /**
     * Gets unique ID of a shipping method
     *
     * @return string Shipping method code
     */
    private function getShippingMethodId(ShippingMethodInterface $method): string
    {
        return $method->getCode() ?? 'method_' . $method->getId();
    }

    /**
     * Calculates shipping method cost for an order
     *
     * Uses the calculator registry to compute cost WITHOUT assigning the method to the shipment.
     * Pattern from Sylius: ShippingMethodChoiceType and ShippingMethodNormalizer
     *
     * @param ShippingMethodInterface $method Shipping method
     * @param OrderInterface $order Order
     *
     * @return int Cost in cents
     */
    private function calculateShippingCost(\Sylius\Component\Shipping\Model\ShippingMethodInterface $method, OrderInterface $order): int
    {
        $shipment = $order->getShipments()->first();
        if ($shipment === false || !$shipment instanceof ShipmentInterface) {
            return 0;
        }

        try {
            $calculatorType = $method->getCalculator();
            if ($calculatorType === null) {
                return 0;
            }

            // Get the calculator for this shipping method type
            /** @var CalculatorInterface $calculator */
            $calculator = $this->calculatorRegistry->get($calculatorType);

            // Calculate cost without modifying the shipment
            // Pattern: calculator->calculate(shipment, configuration)
            return $calculator->calculate($shipment, $method->getConfiguration());
        } catch (\Exception $e) {
            // If calculator not found or calculation fails, return 0
            return 0;
        }
    }
}
