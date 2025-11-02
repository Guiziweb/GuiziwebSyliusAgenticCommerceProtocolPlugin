<?php

declare(strict_types=1);

namespace Tests\Guiziweb\SyliusAgenticCommerceProtocolPlugin\Unit\Applier;

use Doctrine\Common\Collections\ArrayCollection;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Applier\ACPAddressApplier;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Applier\ACPOrderApplier;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Component\Addressing\Repository\ProvinceRepositoryInterface;
use Sylius\Component\Core\Factory\AddressFactoryInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Sylius\Component\Core\Repository\ProductVariantRepositoryInterface;
use Sylius\Component\Core\Repository\ShippingMethodRepositoryInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * Tests for ACPOrderApplier
 *
 * Validates correct application of ACP data according to spec:
 * - Item structure (spec lines 313-319): {id, quantity}
 * - Address structure (spec lines 290-301): line_one, line_two (NOT address_line_1/2)
 * - NO phone field in Address
 */
final class ACPOrderApplierTest extends TestCase
{
    private ProductVariantRepositoryInterface $productVariantRepository;
    private FactoryInterface $orderItemFactory;
    private OrderItemQuantityModifierInterface $orderItemQuantityModifier;
    private AddressFactoryInterface $addressFactory;
    private ProvinceRepositoryInterface $provinceRepository;
    private ACPAddressApplier $addressApplier;
    private ShippingMethodRepositoryInterface $shippingMethodRepository;
    private LoggerInterface $logger;
    private ACPOrderApplier $applier;

    protected function setUp(): void
    {
        $this->productVariantRepository = $this->createMock(ProductVariantRepositoryInterface::class);
        $this->orderItemFactory = $this->createMock(FactoryInterface::class);
        $this->orderItemQuantityModifier = $this->createMock(OrderItemQuantityModifierInterface::class);
        $this->addressFactory = $this->createMock(AddressFactoryInterface::class);
        $this->provinceRepository = $this->createMock(ProvinceRepositoryInterface::class);
        $this->shippingMethodRepository = $this->createMock(ShippingMethodRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Create real ACPAddressApplier with mocked dependencies
        $this->addressApplier = new ACPAddressApplier(
            $this->addressFactory,
            $this->provinceRepository
        );

        $this->applier = new ACPOrderApplier(
            $this->productVariantRepository,
            $this->orderItemFactory,
            $this->orderItemQuantityModifier,
            $this->addressApplier,
            $this->shippingMethodRepository,
            $this->logger
        );
    }

    public function test_it_applies_items_with_correct_structure(): void
    {
        // Given - ACP data with items
        $acpData = [
            'items' => [
                ['id' => 'SKU123', 'quantity' => 2],
                ['id' => 'SKU456', 'quantity' => 1],
            ],
        ];

        $order = $this->createMock(OrderInterface::class);
        $variant1 = $this->createMock(ProductVariantInterface::class);
        $variant2 = $this->createMock(ProductVariantInterface::class);
        $orderItem1 = $this->createMock(OrderItemInterface::class);
        $orderItem2 = $this->createMock(OrderItemInterface::class);

        // Order starts empty
        $order->method('getItems')->willReturn(new ArrayCollection([]));

        // Find variants
        $this->productVariantRepository
            ->method('findOneBy')
            ->willReturnMap([
                [['code' => 'SKU123'], $variant1],
                [['code' => 'SKU456'], $variant2],
            ]);

        // Create order items
        $this->orderItemFactory
            ->method('createNew')
            ->willReturnOnConsecutiveCalls($orderItem1, $orderItem2);

        // Expect items to be added
        $order->expects($this->exactly(2))->method('addItem');

        // When
        $this->applier->apply($acpData, $order);

        // Then - Verified via expects above
        $this->assertTrue(true);
    }

    public function test_it_applies_fulfillment_address_with_correct_field_names(): void
    {
        // Given - ACP data with address using line_one/line_two (NOT address_line_1/2)
        $acpData = [
            'fulfillment_address' => [
                'name' => 'John Doe',
                'line_one' => '123 Main Street',
                'line_two' => 'Apt 4B',
                'city' => 'New York',
                'state' => 'NY',
                'country' => 'US',
                'postal_code' => '10001',
            ],
        ];

        $order = $this->createMock(OrderInterface::class);
        $address = $this->createMock(AddressInterface::class);

        $this->addressFactory->method('createNew')->willReturn($address);

        // Mock province to exist so setProvinceCode is called
        $province = $this->createMock(\Sylius\Component\Addressing\Model\ProvinceInterface::class);
        $this->provinceRepository->method('findOneBy')->with(['code' => 'NY'])->willReturn($province);

        // Verify correct field names are used
        $address->expects($this->once())->method('setFirstName')->with('John');
        $address->expects($this->once())->method('setLastName')->with('Doe');
        // setStreet will be called twice: once for line_one, once for line_one + line_two
        $address->method('getStreet')->willReturn('123 Main Street');
        $address->expects($this->exactly(2))->method('setStreet');
        $address->expects($this->once())->method('setCity')->with('New York');
        $address->expects($this->once())->method('setProvinceCode')->with('NY');
        $address->expects($this->once())->method('setCountryCode')->with('US');
        $address->expects($this->once())->method('setPostcode')->with('10001');

        // Expect address to be set on order
        $order->expects($this->once())->method('setShippingAddress');
        $order->expects($this->once())->method('setBillingAddress');

        // When
        $this->applier->apply($acpData, $order);
    }

    public function test_it_concatenates_line_two_to_street(): void
    {
        // Given
        $acpData = [
            'fulfillment_address' => [
                'name' => 'Jane Doe',
                'line_one' => '456 Oak Avenue',
                'line_two' => 'Suite 200',
                'city' => 'Boston',
                'state' => 'MA',
                'country' => 'US',
                'postal_code' => '02101',
            ],
        ];

        $order = $this->createMock(OrderInterface::class);
        $address = $this->createMock(AddressInterface::class);

        $this->addressFactory->method('createNew')->willReturn($address);

        // Mock getStreet to return line_one after first setStreet call
        $address->method('getStreet')->willReturn('456 Oak Avenue');

        // Should call setStreet twice (for line_one, then line_one + line_two)
        $address->expects($this->exactly(2))->method('setStreet');

        $order->expects($this->once())->method('setShippingAddress');
        $order->expects($this->once())->method('setBillingAddress');

        // When
        $this->applier->apply($acpData, $order);
    }

    public function test_it_does_not_set_phone_on_address(): void
    {
        // Given - ACP Address does NOT have phone field (spec lines 290-301)
        $acpData = [
            'fulfillment_address' => [
                'name' => 'Test User',
                'line_one' => '789 Test St',
                'city' => 'TestCity',
                'state' => 'TS',
                'country' => 'US',
                'postal_code' => '12345',
            ],
        ];

        $order = $this->createMock(OrderInterface::class);
        $address = $this->createMock(AddressInterface::class);

        $this->addressFactory->method('createNew')->willReturn($address);

        // Verify setPhoneNumber is NEVER called
        $address->expects($this->never())->method('setPhoneNumber');

        $order->expects($this->once())->method('setShippingAddress');
        $order->expects($this->once())->method('setBillingAddress');

        // When
        $this->applier->apply($acpData, $order);
    }

    public function test_it_applies_fulfillment_option(): void
    {
        // Given
        $acpData = [
            'fulfillment_option_id' => 'express_shipping',
        ];

        $order = $this->createMock(OrderInterface::class);
        $shipment = $this->createMock(ShipmentInterface::class);
        $shippingMethod = $this->createMock(ShippingMethodInterface::class);

        $order->method('getShipments')->willReturn(new ArrayCollection([$shipment]));

        $this->shippingMethodRepository
            ->method('findOneBy')
            ->with(['code' => 'express_shipping'])
            ->willReturn($shippingMethod);

        // Expect shipping method to be set
        $shipment->expects($this->once())->method('setMethod')->with($shippingMethod);

        // When
        $this->applier->apply($acpData, $order);
    }

    public function test_it_handles_missing_fulfillment_option_gracefully(): void
    {
        // Given - Invalid shipping method code
        $acpData = [
            'fulfillment_option_id' => 'invalid_method',
        ];

        $order = $this->createMock(OrderInterface::class);
        $shipment = $this->createMock(ShipmentInterface::class);

        $order->method('getShipments')->willReturn(new ArrayCollection([$shipment]));

        $this->shippingMethodRepository
            ->method('findOneBy')
            ->with(['code' => 'invalid_method'])
            ->willReturn(null);

        // Should not throw exception, just silently skip
        $shipment->expects($this->never())->method('setMethod');

        // When
        $this->applier->apply($acpData, $order);

        // Then - No exception thrown
        $this->assertTrue(true);
    }

    public function test_it_skips_items_without_id_or_quantity(): void
    {
        // Given - Invalid items
        $acpData = [
            'items' => [
                ['id' => 'SKU123'], // Missing quantity
                ['quantity' => 2], // Missing id
                ['id' => 'SKU456', 'quantity' => 0], // Zero quantity
                ['id' => 'SKU789', 'quantity' => 1], // Valid
            ],
        ];

        $order = $this->createMock(OrderInterface::class);
        $variant = $this->createMock(ProductVariantInterface::class);
        $orderItem = $this->createMock(OrderItemInterface::class);

        $order->method('getItems')->willReturn(new ArrayCollection([]));

        $this->productVariantRepository
            ->method('findOneBy')
            ->with(['code' => 'SKU789'])
            ->willReturn($variant);

        $this->orderItemFactory->method('createNew')->willReturn($orderItem);

        // Should only add 1 valid item
        $order->expects($this->once())->method('addItem')->with($orderItem);

        // When
        $this->applier->apply($acpData, $order);
    }

    public function test_it_skips_items_with_missing_variant(): void
    {
        // Given
        $acpData = [
            'items' => [
                ['id' => 'UNKNOWN_SKU', 'quantity' => 1],
            ],
        ];

        $order = $this->createMock(OrderInterface::class);
        $order->method('getItems')->willReturn(new ArrayCollection([]));

        // Variant not found
        $this->productVariantRepository
            ->method('findOneBy')
            ->with(['code' => 'UNKNOWN_SKU'])
            ->willReturn(null);

        // Should not add any item
        $order->expects($this->never())->method('addItem');

        // When
        $this->applier->apply($acpData, $order);
    }

    public function test_it_clears_existing_items_before_applying_new_ones(): void
    {
        // Given
        $acpData = [
            'items' => [
                ['id' => 'SKU123', 'quantity' => 1],
            ],
        ];

        $order = $this->createMock(OrderInterface::class);
        $existingItem = $this->createMock(OrderItemInterface::class);
        $variant = $this->createMock(ProductVariantInterface::class);
        $newItem = $this->createMock(OrderItemInterface::class);

        // Order has existing items
        $order->method('getItems')->willReturn(new ArrayCollection([$existingItem]));

        $this->productVariantRepository
            ->method('findOneBy')
            ->willReturn($variant);

        $this->orderItemFactory->method('createNew')->willReturn($newItem);

        // Expect existing item to be removed
        $order->expects($this->once())->method('removeItem')->with($existingItem);

        // And new item to be added
        $order->expects($this->once())->method('addItem')->with($newItem);

        // When
        $this->applier->apply($acpData, $order);
    }

    public function test_it_handles_empty_acp_data(): void
    {
        // Given - No data
        $acpData = [];

        $order = $this->createMock(OrderInterface::class);

        // Should not throw any errors
        $this->applier->apply($acpData, $order);

        $this->assertTrue(true);
    }

    public function test_it_parses_name_into_first_and_last_name(): void
    {
        // Given
        $acpData = [
            'fulfillment_address' => [
                'name' => 'John Jacob Doe Smith',
                'line_one' => '123 Test',
                'city' => 'TestCity',
                'state' => 'TS',
                'country' => 'US',
                'postal_code' => '12345',
            ],
        ];

        $order = $this->createMock(OrderInterface::class);
        $address = $this->createMock(AddressInterface::class);

        $this->addressFactory->method('createNew')->willReturn($address);

        // Should split on first space only
        $address->expects($this->once())->method('setFirstName')->with('John');
        $address->expects($this->once())->method('setLastName')->with('Jacob Doe Smith');

        $order->expects($this->once())->method('setShippingAddress');
        $order->expects($this->once())->method('setBillingAddress');

        // When
        $this->applier->apply($acpData, $order);
    }

    public function test_it_omits_line_two_when_empty(): void
    {
        // Given
        $acpData = [
            'fulfillment_address' => [
                'name' => 'Test User',
                'line_one' => '123 Test St',
                'line_two' => '', // Empty line_two
                'city' => 'TestCity',
                'state' => 'TS',
                'country' => 'US',
                'postal_code' => '12345',
            ],
        ];

        $order = $this->createMock(OrderInterface::class);
        $address = $this->createMock(AddressInterface::class);

        $this->addressFactory->method('createNew')->willReturn($address);

        // Should only call setStreet once (for line_one)
        $address->expects($this->once())->method('setStreet')->with('123 Test St');

        $order->expects($this->once())->method('setShippingAddress');
        $order->expects($this->once())->method('setBillingAddress');

        // When
        $this->applier->apply($acpData, $order);
    }

    public function test_it_uppercases_country_code(): void
    {
        // Given - Country code in lowercase (as per ACP spec)
        $acpData = [
            'fulfillment_address' => [
                'name' => 'Test User',
                'line_one' => '123 Test',
                'city' => 'TestCity',
                'state' => 'TS',
                'country' => 'us', // lowercase
                'postal_code' => '12345',
            ],
        ];

        $order = $this->createMock(OrderInterface::class);
        $address = $this->createMock(AddressInterface::class);

        $this->addressFactory->method('createNew')->willReturn($address);

        // Should uppercase country code for Sylius
        $address->expects($this->once())->method('setCountryCode')->with('US');

        $order->expects($this->once())->method('setShippingAddress');
        $order->expects($this->once())->method('setBillingAddress');

        // When
        $this->applier->apply($acpData, $order);
    }
}