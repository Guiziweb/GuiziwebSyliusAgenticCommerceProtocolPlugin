<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Applier;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Applies ACP buyer data to a Sylius Order
 *
 * Responsibilities:
 * - Create or retrieve Customer from buyer email
 * - Apply buyer contact information
 * - Set billing address if provided
 *
 * ACP Spec: POST /checkout_sessions/{id}/complete buyer field (openapi.agentic_checkout.yaml)
 */
final readonly class ACPBuyerApplier
{
    /**
     * @param CustomerRepositoryInterface<CustomerInterface> $customerRepository
     */
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        #[Autowire(service: 'sylius.factory.customer')]
        private FactoryInterface $customerFactory,
        private ACPAddressApplier $addressApplier,
    ) {
    }

    /**
     * Applies ACP buyer data to a Sylius Order
     *
     * @param array<string, mixed> $buyerData ACP buyer data (email, first_name, last_name, phone_number)
     * @param OrderInterface $order Sylius order to modify
     */
    public function apply(array $buyerData, OrderInterface $order): void
    {
        // 1. Get or create customer from email
        // ACP Buyer spec line 303-311: first_name, last_name, email, phone_number
        if (!isset($buyerData['email']) || !is_string($buyerData['email'])) {
            return; // Email is required
        }

        $customer = $this->getOrCreateCustomer($buyerData['email']);

        // 2. Update customer information if provided
        if (isset($buyerData['first_name']) && is_string($buyerData['first_name'])) {
            $customer->setFirstName($buyerData['first_name']);
        }

        if (isset($buyerData['last_name']) && is_string($buyerData['last_name'])) {
            $customer->setLastName($buyerData['last_name']);
        }

        if (isset($buyerData['phone_number']) && is_string($buyerData['phone_number'])) {
            $customer->setPhoneNumber($buyerData['phone_number']);
        }

        // 3. Associate customer with order
        $order->setCustomer($customer);

        // 4. Apply billing address if provided
        if (isset($buyerData['billing_address']) && is_array($buyerData['billing_address'])) {
            $this->applyBillingAddress($buyerData['billing_address'], $order);
        }
    }

    /**
     * Gets existing customer or creates a new one
     *
     * @param string $email Customer email
     *
     * @return CustomerInterface Customer entity
     */
    private function getOrCreateCustomer(string $email): CustomerInterface
    {
        $customer = $this->customerRepository->findOneBy(['email' => $email]);

        if ($customer instanceof CustomerInterface) {
            return $customer;
        }

        // Create new customer
        /** @var CustomerInterface $customer */
        $customer = $this->customerFactory->createNew();
        $customer->setEmail($email);

        return $customer;
    }

    /**
     * Applies ACP billing address to the order
     *
     * @param array<string, mixed> $addressData ACP billing address
     * @param OrderInterface $order Sylius order
     */
    private function applyBillingAddress(array $addressData, OrderInterface $order): void
    {
        // Use ACPAddressApplier service to create and populate address
        $address = $this->addressApplier->createAddress($addressData);

        // Set as billing address (keep shipping address unchanged)
        $order->setBillingAddress($address);
    }
}
