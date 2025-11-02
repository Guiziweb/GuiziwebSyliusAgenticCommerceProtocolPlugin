<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Applier;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Repository\ProductVariantRepositoryInterface;
use Sylius\Component\Core\Repository\ShippingMethodRepositoryInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Applies ACP data to a Sylius Order
 *
 * Responsibilities:
 * - Parse and apply line_items (add/modify items)
 * - Apply fulfillment address
 * - Select shipping method if provided
 *
 * ACP Spec: POST /checkout_sessions body (openapi.agentic_checkout.yaml)
 */
final readonly class ACPOrderApplier
{
    /**
     * @param ProductVariantRepositoryInterface<ProductVariantInterface> $productVariantRepository
     * @param ShippingMethodRepositoryInterface<\Sylius\Component\Core\Model\ShippingMethodInterface> $shippingMethodRepository
     */
    public function __construct(
        private ProductVariantRepositoryInterface $productVariantRepository,
        #[Autowire(service: 'sylius.factory.order_item')]
        private FactoryInterface $orderItemFactory,
        private OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        private ACPAddressApplier $addressApplier,
        private ShippingMethodRepositoryInterface $shippingMethodRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Applies ACP data to a Sylius Order
     *
     * @param array<string, mixed> $acpData ACP data (items, fulfillment_address, etc.)
     * @param OrderInterface $order Sylius order to modify
     */
    public function apply(array $acpData, OrderInterface $order): void
    {
        // 1. Apply line items
        if (isset($acpData['items']) && is_array($acpData['items'])) {
            $this->applyLineItems($acpData['items'], $order);
        }

        // 2. Apply fulfillment address
        if (isset($acpData['fulfillment_address']) && is_array($acpData['fulfillment_address'])) {
            $this->applyFulfillmentAddress($acpData['fulfillment_address'], $order);
        }

        // 3. Apply selected shipping method
        if (isset($acpData['fulfillment_option_id']) && is_string($acpData['fulfillment_option_id'])) {
            $this->applyFulfillmentOption($acpData['fulfillment_option_id'], $order);
        }
    }

    /**
     * Applies ACP line items to the order
     *
     * @param array<int, array<string, mixed>> $items ACP items
     * @param OrderInterface $order Sylius order
     */
    private function applyLineItems(array $items, OrderInterface $order): void
    {
        // Clear existing items (strategy: start from scratch)
        // Alternative: merge with existing items
        foreach ($order->getItems() as $item) {
            $order->removeItem($item);
        }

        foreach ($items as $itemData) {
            if (!isset($itemData['id']) || !isset($itemData['quantity'])) {
                continue; // Skip invalid items
            }

            $variantCode = $itemData['id'];
            /** @var int|string $rawQuantity */
            $rawQuantity = $itemData['quantity'];
            $quantity = (int) $rawQuantity;

            if ($quantity <= 0) {
                continue;
            }

            // Find variant by code
            $variant = $this->productVariantRepository->findOneBy(['code' => $variantCode]);
            if (!$variant instanceof ProductVariantInterface) {
                $this->logger->warning('ACP: Product variant not found, skipping item', [
                    'variant_code' => $variantCode,
                    'order_id' => $order->getId(),
                ]);

                continue;
            }

            // Create OrderItem
            /** @var OrderItemInterface $orderItem */
            $orderItem = $this->orderItemFactory->createNew();
            $orderItem->setVariant($variant);

            // Set quantity
            $this->orderItemQuantityModifier->modify($orderItem, $quantity);

            // Add to order
            $order->addItem($orderItem);
        }
    }

    /**
     * Applies ACP fulfillment address to the order
     *
     * @param array<string, mixed> $addressData ACP address
     * @param OrderInterface $order Sylius order
     */
    private function applyFulfillmentAddress(array $addressData, OrderInterface $order): void
    {
        // Use ACPAddressApplier service to create and populate address
        $address = $this->addressApplier->createAddress($addressData);

        // Apply as both shipping address AND billing address
        $order->setShippingAddress(clone $address);
        $order->setBillingAddress($address);
    }

    /**
     * Applies the selected fulfillment option (shipping method)
     *
     * @param string $fulfillmentOptionId Shipping method code
     * @param OrderInterface $order Sylius order
     */
    private function applyFulfillmentOption(string $fulfillmentOptionId, OrderInterface $order): void
    {
        // Find shipping method by code
        $shippingMethod = $this->shippingMethodRepository->findOneBy(['code' => $fulfillmentOptionId]);
        if ($shippingMethod === null) {
            $this->logger->warning('ACP: Shipping method not found, skipping fulfillment option', [
                'fulfillment_option_id' => $fulfillmentOptionId,
                'order_id' => $order->getId(),
            ]);

            return;
        }

        // Apply to first shipment
        $shipment = $order->getShipments()->first();
        if ($shipment === false) {
            // No shipment, it will be created by OrderProcessor
            return;
        }

        $shipment->setMethod($shippingMethod);
    }
}
