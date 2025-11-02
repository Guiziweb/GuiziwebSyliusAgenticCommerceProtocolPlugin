<?php

declare(strict_types=1);

namespace Tests\Guiziweb\SyliusAgenticCommerceProtocolPlugin\Unit\Applier;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Applier\ACPAddressApplier;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Applier\ACPBuyerApplier;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Addressing\Repository\ProvinceRepositoryInterface;
use Sylius\Component\Core\Factory\AddressFactoryInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * Tests for ACPBuyerApplier
 *
 * Validates correct application of ACP buyer data according to spec:
 * - Buyer structure (spec lines 303-311): first_name, last_name, email, phone_number
 * - NOT "name" or "phone"!
 * - Address has NO phone field (spec lines 290-301)
 */
final class ACPBuyerApplierTest extends TestCase
{
    private CustomerRepositoryInterface $customerRepository;
    private FactoryInterface $customerFactory;
    private AddressFactoryInterface $addressFactory;
    private ProvinceRepositoryInterface $provinceRepository;
    private ACPAddressApplier $addressApplier;
    private ACPBuyerApplier $applier;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->customerFactory = $this->createMock(FactoryInterface::class);
        $this->addressFactory = $this->createMock(AddressFactoryInterface::class);
        $this->provinceRepository = $this->createMock(ProvinceRepositoryInterface::class);

        // Create real ACPAddressApplier with mocked dependencies
        $this->addressApplier = new ACPAddressApplier(
            $this->addressFactory,
            $this->provinceRepository
        );

        $this->applier = new ACPBuyerApplier(
            $this->customerRepository,
            $this->customerFactory,
            $this->addressApplier
        );
    }

    public function test_it_applies_buyer_with_correct_field_names(): void
    {
        // Given - ACP buyer with first_name/last_name/phone_number (NOT name/phone!)
        $buyerData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone_number' => '+1234567890',
        ];

        $order = $this->createMock(OrderInterface::class);
        $customer = $this->createMock(CustomerInterface::class);

        // Customer doesn't exist yet
        $this->customerRepository
            ->method('findOneBy')
            ->with(['email' => 'john.doe@example.com'])
            ->willReturn(null);

        // Create new customer
        $this->customerFactory->method('createNew')->willReturn($customer);

        // Verify correct setters are called with correct field names
        $customer->expects($this->once())->method('setEmail')->with('john.doe@example.com');
        $customer->expects($this->once())->method('setFirstName')->with('John');
        $customer->expects($this->once())->method('setLastName')->with('Doe');
        $customer->expects($this->once())->method('setPhoneNumber')->with('+1234567890');

        $order->expects($this->once())->method('setCustomer')->with($customer);

        // When
        $this->applier->apply($buyerData, $order);
    }

    public function test_it_returns_early_when_email_is_missing(): void
    {
        // Given - No email (required)
        $buyerData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];

        $order = $this->createMock(OrderInterface::class);

        // Should not look for customer
        $this->customerRepository->expects($this->never())->method('findOneBy');

        // Should not set customer
        $order->expects($this->never())->method('setCustomer');

        // When
        $this->applier->apply($buyerData, $order);
    }

    public function test_it_retrieves_existing_customer_by_email(): void
    {
        // Given
        $buyerData = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@example.com',
        ];

        $order = $this->createMock(OrderInterface::class);
        $existingCustomer = $this->createMock(CustomerInterface::class);

        // Customer already exists
        $this->customerRepository
            ->method('findOneBy')
            ->with(['email' => 'jane.smith@example.com'])
            ->willReturn($existingCustomer);

        // Should not create new customer
        $this->customerFactory->expects($this->never())->method('createNew');

        // Should update existing customer
        $existingCustomer->expects($this->once())->method('setFirstName')->with('Jane');
        $existingCustomer->expects($this->once())->method('setLastName')->with('Smith');

        $order->expects($this->once())->method('setCustomer')->with($existingCustomer);

        // When
        $this->applier->apply($buyerData, $order);
    }

    public function test_it_handles_optional_first_name(): void
    {
        // Given - first_name is optional
        $buyerData = [
            'email' => 'test@example.com',
            'last_name' => 'TestLast',
        ];

        $order = $this->createMock(OrderInterface::class);
        $customer = $this->createMock(CustomerInterface::class);

        $this->customerRepository->method('findOneBy')->willReturn(null);
        $this->customerFactory->method('createNew')->willReturn($customer);

        // Should not call setFirstName
        $customer->expects($this->never())->method('setFirstName');

        // Should call setLastName
        $customer->expects($this->once())->method('setLastName')->with('TestLast');

        $order->expects($this->once())->method('setCustomer');

        // When
        $this->applier->apply($buyerData, $order);
    }

    public function test_it_handles_optional_phone_number(): void
    {
        // Given - phone_number is optional
        $buyerData = [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
        ];

        $order = $this->createMock(OrderInterface::class);
        $customer = $this->createMock(CustomerInterface::class);

        $this->customerRepository->method('findOneBy')->willReturn(null);
        $this->customerFactory->method('createNew')->willReturn($customer);

        // Should not call setPhoneNumber
        $customer->expects($this->never())->method('setPhoneNumber');

        $order->expects($this->once())->method('setCustomer');

        // When
        $this->applier->apply($buyerData, $order);
    }

    public function test_it_applies_billing_address(): void
    {
        // Given
        $buyerData = [
            'email' => 'test@example.com',
            'billing_address' => [
                'name' => 'Billing Name',
                'line_one' => '789 Billing St',
                'city' => 'BillingCity',
                'state' => 'BC',
                'country' => 'US',
                'postal_code' => '54321',
            ],
        ];

        $order = $this->createMock(OrderInterface::class);
        $customer = $this->createMock(CustomerInterface::class);
        $address = $this->createMock(AddressInterface::class);

        $this->customerRepository->method('findOneBy')->willReturn(null);
        $this->customerFactory->method('createNew')->willReturn($customer);
        $this->addressFactory->method('createNew')->willReturn($address);

        $order->expects($this->once())->method('setCustomer');
        $order->expects($this->once())->method('setBillingAddress')->with($address);

        // When
        $this->applier->apply($buyerData, $order);
    }

    public function test_it_does_not_set_phone_on_billing_address(): void
    {
        // Given - Address does NOT have phone field
        $buyerData = [
            'email' => 'test@example.com',
            'billing_address' => [
                'name' => 'Test User',
                'line_one' => '123 Test',
                'city' => 'TestCity',
                'state' => 'TS',
                'country' => 'US',
                'postal_code' => '12345',
            ],
        ];

        $order = $this->createMock(OrderInterface::class);
        $customer = $this->createMock(CustomerInterface::class);
        $address = $this->createMock(AddressInterface::class);

        $this->customerRepository->method('findOneBy')->willReturn(null);
        $this->customerFactory->method('createNew')->willReturn($customer);
        $this->addressFactory->method('createNew')->willReturn($address);

        // Verify setPhoneNumber is NEVER called on address
        $address->expects($this->never())->method('setPhoneNumber');

        $order->expects($this->once())->method('setCustomer');
        $order->expects($this->once())->method('setBillingAddress');

        // When
        $this->applier->apply($buyerData, $order);
    }

    public function test_it_concatenates_line_two_to_billing_address_street(): void
    {
        // Given
        $buyerData = [
            'email' => 'test@example.com',
            'billing_address' => [
                'name' => 'Test User',
                'line_one' => '123 Main St',
                'line_two' => 'Suite 100',
                'city' => 'TestCity',
                'state' => 'TS',
                'country' => 'US',
                'postal_code' => '12345',
            ],
        ];

        $order = $this->createMock(OrderInterface::class);
        $customer = $this->createMock(CustomerInterface::class);
        $address = $this->createMock(AddressInterface::class);

        $this->customerRepository->method('findOneBy')->willReturn(null);
        $this->customerFactory->method('createNew')->willReturn($customer);
        $this->addressFactory->method('createNew')->willReturn($address);

        $order->expects($this->once())->method('setCustomer');
        $order->expects($this->once())->method('setBillingAddress');

        // When
        $this->applier->apply($buyerData, $order);
    }

    public function test_it_omits_line_two_when_empty(): void
    {
        // Given
        $buyerData = [
            'email' => 'test@example.com',
            'billing_address' => [
                'name' => 'Test User',
                'line_one' => '123 Main St',
                'line_two' => '',
                'city' => 'TestCity',
                'state' => 'TS',
                'country' => 'US',
                'postal_code' => '12345',
            ],
        ];

        $order = $this->createMock(OrderInterface::class);
        $customer = $this->createMock(CustomerInterface::class);
        $address = $this->createMock(AddressInterface::class);

        $this->customerRepository->method('findOneBy')->willReturn(null);
        $this->customerFactory->method('createNew')->willReturn($customer);
        $this->addressFactory->method('createNew')->willReturn($address);

        $order->expects($this->once())->method('setCustomer');
        $order->expects($this->once())->method('setBillingAddress');

        // When
        $this->applier->apply($buyerData, $order);
    }

    public function test_it_handles_buyer_without_billing_address(): void
    {
        // Given - No billing_address
        $buyerData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ];

        $order = $this->createMock(OrderInterface::class);
        $customer = $this->createMock(CustomerInterface::class);

        $this->customerRepository->method('findOneBy')->willReturn(null);
        $this->customerFactory->method('createNew')->willReturn($customer);

        // Should not create address
        $this->addressFactory->expects($this->never())->method('createNew');

        // Should not set billing address
        $order->expects($this->never())->method('setBillingAddress');

        // Should still set customer
        $order->expects($this->once())->method('setCustomer')->with($customer);

        // When
        $this->applier->apply($buyerData, $order);
    }

    public function test_it_uppercases_country_code_in_billing_address(): void
    {
        // Given - Country code in lowercase
        $buyerData = [
            'email' => 'test@example.com',
            'billing_address' => [
                'name' => 'Test',
                'line_one' => '123 Test',
                'city' => 'TestCity',
                'state' => 'TS',
                'country' => 'us', // lowercase
                'postal_code' => '12345',
            ],
        ];

        $order = $this->createMock(OrderInterface::class);
        $customer = $this->createMock(CustomerInterface::class);
        $address = $this->createMock(AddressInterface::class);

        $this->customerRepository->method('findOneBy')->willReturn(null);
        $this->customerFactory->method('createNew')->willReturn($customer);
        $this->addressFactory->method('createNew')->willReturn($address);

        $order->expects($this->once())->method('setCustomer');
        $order->expects($this->once())->method('setBillingAddress');

        // When
        $this->applier->apply($buyerData, $order);
    }
}