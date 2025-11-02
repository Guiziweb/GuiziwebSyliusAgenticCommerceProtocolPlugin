<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Resolver;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Enum\ACPStatus;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderPaymentStates;

/**
 * Resolves ACP status from a Sylius Order
 *
 * Mapping Sylius → ACP:
 * - state=cart + missing address → not_ready_for_payment
 * - state=cart + address OK → ready_for_payment
 * - checkoutState=completed → in_progress
 * - state=new + paymentState=paid → completed
 * - state=cancelled → canceled
 *
 * ACP Spec: CheckoutSessionBase.status (openapi.agentic_checkout.yaml)
 */
final readonly class ACPStatusResolver implements ACPStatusResolverInterface
{
    /**
     * Resolves the ACP status of a Sylius order
     *
     * @param OrderInterface $order Sylius order (state=cart or new)
     *
     * @return string ACP status (not_ready_for_payment|ready_for_payment|in_progress|completed|canceled)
     */
    public function resolve(OrderInterface $order): string
    {
        // Canceled order
        if ($order->getState() === OrderInterface::STATE_CANCELLED) {
            return ACPStatus::CANCELED->value;
        }

        // Finalized and paid order
        if ($order->getState() === OrderInterface::STATE_NEW &&
            $order->getPaymentState() === OrderPaymentStates::STATE_PAID
        ) {
            return ACPStatus::COMPLETED->value;
        }

        // Checkout in progress (payment processing)
        if ($order->getCheckoutState() === OrderCheckoutStates::STATE_COMPLETED) {
            return ACPStatus::IN_PROGRESS->value;
        }

        // Cart state: check if ready for payment
        if ($order->getState() === OrderInterface::STATE_CART) {
            return $this->resolveCartStatus($order);
        }

        // Fallback: new but not yet paid
        return ACPStatus::IN_PROGRESS->value;
    }

    /**
     * Resolves the status for an order in "cart" state
     *
     * @param OrderInterface $order Order in cart state
     *
     * @return string not_ready_for_payment or ready_for_payment
     */
    private function resolveCartStatus(OrderInterface $order): string
    {
        // Check if shipping address is defined
        if ($order->getShippingAddress() === null) {
            return ACPStatus::NOT_READY_FOR_PAYMENT->value;
        }

        // Check if address is complete (at least street and city)
        $address = $order->getShippingAddress();
        if (empty($address->getStreet()) || empty($address->getCity())) {
            return ACPStatus::NOT_READY_FOR_PAYMENT->value;
        }

        // Check if there is at least one item
        if ($order->getItems()->isEmpty()) {
            return ACPStatus::NOT_READY_FOR_PAYMENT->value;
        }

        // Check if shipping method is selected (if needed)
        if (!$order->getShipments()->isEmpty()) {
            foreach ($order->getShipments() as $shipment) {
                if ($shipment->getMethod() === null) {
                    return ACPStatus::NOT_READY_FOR_PAYMENT->value;
                }
            }
        }

        // Everything is ready for payment
        return ACPStatus::READY_FOR_PAYMENT->value;
    }
}
