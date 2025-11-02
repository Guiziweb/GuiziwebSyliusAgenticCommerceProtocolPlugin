<?php

declare(strict_types=1);

namespace Tests\Guiziweb\SyliusAgenticCommerceProtocolPlugin\Unit\Serializer\Normalizer;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Entity\ACPCheckoutSession;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Mapper\ACPFulfillmentMapperInterface;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Mapper\ACPLineItemsMapperInterface;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Mapper\ACPTotalsMapperInterface;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Resolver\ACPStatusResolverInterface;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Serializer\Normalizer\ACPCheckoutSessionNormalizer;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Tests for ACPCheckoutSessionNormalizer
 *
 * Validates that the normalizer produces complete ACP JSON responses according to spec:
 * - CheckoutSessionBase structure (openapi.agentic_checkout.yaml lines 232-281)
 * - All required fields present with correct types
 * - Proper integration of all mappers
 */
final class ACPCheckoutSessionNormalizerTest extends TestCase
{
    private ACPStatusResolverInterface $statusResolver;
    private ACPTotalsMapperInterface $totalsMapper;
    private ACPLineItemsMapperInterface $lineItemsMapper;
    private ACPFulfillmentMapperInterface $fulfillmentMapper;
    private PaymentMethodRepositoryInterface $paymentMethodRepository;
    private UrlGeneratorInterface $urlGenerator;
    private ACPCheckoutSessionNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->statusResolver = $this->createMock(ACPStatusResolverInterface::class);
        $this->totalsMapper = $this->createMock(ACPTotalsMapperInterface::class);
        $this->lineItemsMapper = $this->createMock(ACPLineItemsMapperInterface::class);
        $this->fulfillmentMapper = $this->createMock(ACPFulfillmentMapperInterface::class);
        $this->paymentMethodRepository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->normalizer = new ACPCheckoutSessionNormalizer(
            $this->statusResolver,
            $this->totalsMapper,
            $this->lineItemsMapper,
            $this->fulfillmentMapper,
            $this->paymentMethodRepository,
            $this->urlGenerator
        );
    }

    public function test_it_normalizes_complete_checkout_session(): void
    {
        // Given
        $session = $this->createMock(ACPCheckoutSession::class);
        $order = $this->createMock(OrderInterface::class);

        $session->method('getAcpId')->willReturn('acp_sess_123456');
        $session->method('getOrder')->willReturn($order);

        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getShippingAddress')->willReturn(null);
        $this->setupChannelForOrder($order);

        // Mock mappers
        $this->statusResolver->method('resolve')->willReturn('ready_for_payment');
        $this->totalsMapper->method('map')->willReturn([
            ['type' => 'items_base_amount', 'display_text' => 'Items', 'amount' => 3000],
            ['type' => 'total', 'display_text' => 'Total', 'amount' => 3000],
        ]);
        $this->lineItemsMapper->method('map')->willReturn([
            ['id' => 'line_item_1', 'item' => ['id' => 'SKU123', 'quantity' => 1], 'total' => 3000],
        ]);
        $this->fulfillmentMapper->method('mapOptions')->willReturn([
            ['type' => 'shipping', 'id' => 'standard', 'title' => 'Standard', 'subtotal' => '5.00', 'tax' => '0.00', 'total' => '5.00'],
        ]);
        $this->fulfillmentMapper->method('mapSelectedOption')->willReturn(null);

        // When
        $result = $this->normalizer->normalize($session);

        // Then - Verify complete ACP structure
        $this->assertIsArray($result);

        // Required fields (spec lines 232-281)
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('currency', $result);
        $this->assertArrayHasKey('line_items', $result);
        $this->assertArrayHasKey('totals', $result);
        $this->assertArrayHasKey('fulfillment_options', $result);
        $this->assertArrayHasKey('messages', $result);
        $this->assertArrayHasKey('links', $result); // REQUIRED per spec line 514
        $this->assertArrayHasKey('payment_provider', $result);

        // Verify values
        $this->assertSame('acp_sess_123456', $result['id']);
        $this->assertSame('ready_for_payment', $result['status']);
        $this->assertSame('usd', $result['currency']); // Lowercase!

        // Verify mapper integration
        $this->assertCount(2, $result['totals']);
        $this->assertCount(1, $result['line_items']);
        $this->assertCount(1, $result['fulfillment_options']);

        // Verify payment_provider structure
        $this->assertArrayHasKey('provider', $result['payment_provider']);
        $this->assertArrayHasKey('supported_payment_methods', $result['payment_provider']);
        $this->assertSame('stripe', $result['payment_provider']['provider']);
    }

    public function test_it_includes_selected_fulfillment_option(): void
    {
        // Given
        $session = $this->createMock(ACPCheckoutSession::class);
        $order = $this->createMock(OrderInterface::class);

        $session->method('getAcpId')->willReturn('acp_sess_123');
        $session->method('getOrder')->willReturn($order);
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getShippingAddress')->willReturn(null);
        $this->setupChannelForOrder($order);

        $this->statusResolver->method('resolve')->willReturn('ready_for_payment');
        $this->totalsMapper->method('map')->willReturn([]);
        $this->lineItemsMapper->method('map')->willReturn([]);
        $this->fulfillmentMapper->method('mapOptions')->willReturn([]);
        $this->fulfillmentMapper->method('mapSelectedOption')->willReturn('express_shipping');

        // When
        $result = $this->normalizer->normalize($session);

        // Then
        $this->assertArrayHasKey('fulfillment_option_id', $result);
        $this->assertSame('express_shipping', $result['fulfillment_option_id']);
    }

    public function test_it_omits_fulfillment_option_id_when_not_selected(): void
    {
        // Given
        $session = $this->createMock(ACPCheckoutSession::class);
        $order = $this->createMock(OrderInterface::class);

        $session->method('getAcpId')->willReturn('acp_sess_123');
        $session->method('getOrder')->willReturn($order);
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getShippingAddress')->willReturn(null);
        $this->setupChannelForOrder($order);

        $this->statusResolver->method('resolve')->willReturn('not_ready_for_payment');
        $this->totalsMapper->method('map')->willReturn([]);
        $this->lineItemsMapper->method('map')->willReturn([]);
        $this->fulfillmentMapper->method('mapOptions')->willReturn([]);
        $this->fulfillmentMapper->method('mapSelectedOption')->willReturn(null);

        // When
        $result = $this->normalizer->normalize($session);

        // Then - Should NOT have fulfillment_option_id key
        $this->assertArrayNotHasKey('fulfillment_option_id', $result);
    }

    public function test_it_includes_fulfillment_address_when_present(): void
    {
        // Given
        $session = $this->createMock(ACPCheckoutSession::class);
        $order = $this->createMock(OrderInterface::class);
        $address = $this->createMock(AddressInterface::class);

        $session->method('getAcpId')->willReturn('acp_sess_123');
        $session->method('getOrder')->willReturn($order);
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getShippingAddress')->willReturn($address);

        // Address data
        $address->method('getFirstName')->willReturn('John');
        $address->method('getLastName')->willReturn('Doe');
        $address->method('getStreet')->willReturn('123 Main St');
        $address->method('getCity')->willReturn('New York');
        $address->method('getProvinceCode')->willReturn('NY');
        $address->method('getCountryCode')->willReturn('US');
        $address->method('getPostcode')->willReturn('10001');
        $this->setupChannelForOrder($order);

        $this->statusResolver->method('resolve')->willReturn('ready_for_payment');
        $this->totalsMapper->method('map')->willReturn([]);
        $this->lineItemsMapper->method('map')->willReturn([]);
        $this->fulfillmentMapper->method('mapOptions')->willReturn([]);
        $this->fulfillmentMapper->method('mapSelectedOption')->willReturn(null);

        // When
        $result = $this->normalizer->normalize($session);

        // Then
        $this->assertArrayHasKey('fulfillment_address', $result);
        $address = $result['fulfillment_address'];

        // Verify ACP address structure
        $this->assertArrayHasKey('name', $address);
        $this->assertArrayHasKey('line_one', $address);
        $this->assertArrayHasKey('city', $address);
        $this->assertArrayHasKey('state', $address);
        $this->assertArrayHasKey('country', $address);
        $this->assertArrayHasKey('postal_code', $address);

        $this->assertSame('John Doe', $address['name']);
        $this->assertSame('123 Main St', $address['line_one']);
        $this->assertSame('New York', $address['city']);
        $this->assertSame('NY', $address['state']);
        $this->assertSame('US', $address['country']); // Uppercase per ACP spec!
        $this->assertSame('10001', $address['postal_code']);

        // Verify NO phone field
        $this->assertArrayNotHasKey('phone', $address);
    }

    public function test_it_splits_street_into_line_one_and_line_two(): void
    {
        // Given
        $session = $this->createMock(ACPCheckoutSession::class);
        $order = $this->createMock(OrderInterface::class);
        $address = $this->createMock(AddressInterface::class);

        $session->method('getAcpId')->willReturn('acp_sess_123');
        $session->method('getOrder')->willReturn($order);
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getShippingAddress')->willReturn($address);

        // Street with newline (from ACPOrderApplier concatenation)
        $address->method('getStreet')->willReturn("123 Main St\nApt 4B");
        $address->method('getFirstName')->willReturn('Jane');
        $address->method('getLastName')->willReturn('Smith');
        $address->method('getCity')->willReturn('Boston');
        $address->method('getCountryCode')->willReturn('US');
        $address->method('getPostcode')->willReturn('02101');
        $this->setupChannelForOrder($order);

        $this->statusResolver->method('resolve')->willReturn('ready_for_payment');
        $this->totalsMapper->method('map')->willReturn([]);
        $this->lineItemsMapper->method('map')->willReturn([]);
        $this->fulfillmentMapper->method('mapOptions')->willReturn([]);
        $this->fulfillmentMapper->method('mapSelectedOption')->willReturn(null);

        // When
        $result = $this->normalizer->normalize($session);

        // Then
        $address = $result['fulfillment_address'];
        $this->assertSame('123 Main St', $address['line_one']);
        $this->assertArrayHasKey('line_two', $address);
        $this->assertSame('Apt 4B', $address['line_two']);
    }

    public function test_it_lowercases_currency_code(): void
    {
        // Given
        $session = $this->createMock(ACPCheckoutSession::class);
        $order = $this->createMock(OrderInterface::class);

        $session->method('getAcpId')->willReturn('acp_sess_123');
        $session->method('getOrder')->willReturn($order);
        $order->method('getCurrencyCode')->willReturn('EUR'); // Uppercase
        $order->method('getShippingAddress')->willReturn(null);
        $this->setupChannelForOrder($order);

        $this->statusResolver->method('resolve')->willReturn('ready_for_payment');
        $this->totalsMapper->method('map')->willReturn([]);
        $this->lineItemsMapper->method('map')->willReturn([]);
        $this->fulfillmentMapper->method('mapOptions')->willReturn([]);
        $this->fulfillmentMapper->method('mapSelectedOption')->willReturn(null);

        // When
        $result = $this->normalizer->normalize($session);

        // Then
        $this->assertSame('eur', $result['currency']); // Lowercase!
    }

    public function test_it_supports_normalization_of_acp_checkout_session(): void
    {
        // Given
        $session = $this->createMock(ACPCheckoutSession::class);

        // When / Then
        $this->assertTrue($this->normalizer->supportsNormalization($session));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function test_it_throws_exception_when_order_is_null(): void
    {
        // Given
        $session = $this->createMock(ACPCheckoutSession::class);
        $session->method('getOrder')->willReturn(null);

        // Expect exception
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('ACPCheckoutSession must have an associated Order');

        // When
        $this->normalizer->normalize($session);
    }

    public function test_it_throws_exception_when_currency_is_null(): void
    {
        // Given
        $session = $this->createMock(ACPCheckoutSession::class);
        $order = $this->createMock(OrderInterface::class);

        $session->method('getAcpId')->willReturn('acp_sess_123');
        $session->method('getOrder')->willReturn($order);
        $order->method('getCurrencyCode')->willReturn(null); // No currency!

        // Expect exception
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Order must have a currency code');

        // When
        $this->normalizer->normalize($session);
    }

    public function test_it_includes_buyer_when_customer_exists(): void
    {
        // Given
        $session = $this->createMock(ACPCheckoutSession::class);
        $order = $this->createMock(OrderInterface::class);
        $customer = $this->createMock(\Sylius\Component\Core\Model\CustomerInterface::class);
        $billingAddress = $this->createMock(AddressInterface::class);

        $session->method('getAcpId')->willReturn('acp_sess_123');
        $session->method('getOrder')->willReturn($order);
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getShippingAddress')->willReturn(null);
        $order->method('getCustomer')->willReturn($customer);
        $order->method('getBillingAddress')->willReturn($billingAddress);

        // Customer data
        $customer->method('getEmail')->willReturn('john@example.com');
        $customer->method('getFirstName')->willReturn('John');
        $customer->method('getLastName')->willReturn('Doe');
        $customer->method('getPhoneNumber')->willReturn('+1234567890');

        // Billing address
        $billingAddress->method('getFirstName')->willReturn('John');
        $billingAddress->method('getLastName')->willReturn('Doe');
        $billingAddress->method('getStreet')->willReturn('456 Billing St');
        $billingAddress->method('getCity')->willReturn('Boston');
        $billingAddress->method('getCountryCode')->willReturn('US');
        $billingAddress->method('getPostcode')->willReturn('02101');
        $billingAddress->method('getProvinceCode')->willReturn(null);

        $this->setupChannelForOrder($order);
        $this->statusResolver->method('resolve')->willReturn('ready_for_payment');
        $this->totalsMapper->method('map')->willReturn([]);
        $this->lineItemsMapper->method('map')->willReturn([]);
        $this->fulfillmentMapper->method('mapOptions')->willReturn([]);
        $this->fulfillmentMapper->method('mapSelectedOption')->willReturn(null);

        // When
        $result = $this->normalizer->normalize($session);

        // Then
        $this->assertArrayHasKey('buyer', $result);
        $buyer = $result['buyer'];

        // Verify REQUIRED fields per Buyer schema (spec lines 303-311)
        $this->assertArrayHasKey('email', $buyer);
        $this->assertArrayHasKey('first_name', $buyer);
        $this->assertArrayHasKey('last_name', $buyer);

        // Verify optional phone_number is present
        $this->assertArrayHasKey('phone_number', $buyer);

        $this->assertSame('john@example.com', $buyer['email']);
        $this->assertSame('John', $buyer['first_name']);
        $this->assertSame('Doe', $buyer['last_name']);
        $this->assertSame('+1234567890', $buyer['phone_number']);

        // billing_address is NOT in Buyer schema - should NOT be present
        $this->assertArrayNotHasKey('billing_address', $buyer);
    }

    public function test_it_omits_buyer_when_no_customer(): void
    {
        // Given
        $session = $this->createMock(ACPCheckoutSession::class);
        $order = $this->createMock(OrderInterface::class);

        $session->method('getAcpId')->willReturn('acp_sess_123');
        $session->method('getOrder')->willReturn($order);
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getShippingAddress')->willReturn(null);
        $order->method('getCustomer')->willReturn(null);

        $this->setupChannelForOrder($order);
        $this->statusResolver->method('resolve')->willReturn('not_ready_for_payment');
        $this->totalsMapper->method('map')->willReturn([]);
        $this->lineItemsMapper->method('map')->willReturn([]);
        $this->fulfillmentMapper->method('mapOptions')->willReturn([]);
        $this->fulfillmentMapper->method('mapSelectedOption')->willReturn(null);

        // When
        $result = $this->normalizer->normalize($session);

        // Then - Should NOT have buyer key when no customer
        $this->assertArrayNotHasKey('buyer', $result);
    }

    /**
     * Helper to setup channel mock for order
     */
    private function setupChannelForOrder(OrderInterface $order): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $order->method('getChannel')->willReturn($channel);
        $this->paymentMethodRepository->method('findEnabledForChannel')->willReturn([]);
    }
}
