<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Serializer\Normalizer;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Entity\ACPCheckoutSession;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Enum\PaymentGatewayFactory;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Mapper\ACPFulfillmentMapperInterface;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Mapper\ACPLineItemsMapperInterface;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Mapper\ACPTotalsMapperInterface;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Resolver\ACPStatusResolverInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes ACPCheckoutSession to ACP JSON format
 *
 * Assembles complete ACP response according to spec:
 * - openapi.agentic_checkout.yaml schema CheckoutSessionBase (lines 232-281)
 * - Combines data from all mappers (totals, line_items, fulfillment_options)
 * - Ensures strict spec compliance for all fields
 */
final readonly class ACPCheckoutSessionNormalizer implements NormalizerInterface
{
    /**
     * @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository
     */
    public function __construct(
        private ACPStatusResolverInterface $statusResolver,
        private ACPTotalsMapperInterface $totalsMapper,
        private ACPLineItemsMapperInterface $lineItemsMapper,
        private ACPFulfillmentMapperInterface $fulfillmentMapper,
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Normalizes ACPCheckoutSession to ACP JSON format
     *
     * @param ACPCheckoutSession $session
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $session, ?string $format = null, array $context = []): array
    {
        if (!$session instanceof ACPCheckoutSession) {
            throw new \InvalidArgumentException('Expected ACPCheckoutSession');
        }

        $order = $session->getOrder();
        if (!$order instanceof OrderInterface) {
            throw new \LogicException('ACPCheckoutSession must have an associated Order');
        }

        // Build complete ACP response according to spec
        // Spec: openapi.agentic_checkout.yaml lines 232-281
        $currencyCode = $order->getCurrencyCode();
        if ($currencyCode === null) {
            throw new \LogicException('Order must have a currency code');
        }

        // Determine status: use session's explicit status if 'canceled' or 'completed',
        // otherwise recalculate dynamically from Order state
        $sessionStatus = $session->getStatus();
        $status = in_array($sessionStatus, ['canceled', 'completed'], true)
            ? $sessionStatus
            : $this->statusResolver->resolve($order);

        $data = [
            'id' => $session->getAcpId(),
            'status' => $status,
            'currency' => strtolower($currencyCode),
            'line_items' => $this->lineItemsMapper->map($order),
            'totals' => $this->totalsMapper->map($order),
            'fulfillment_options' => $this->fulfillmentMapper->mapOptions($order),
            'messages' => [],
            'links' => [],
        ];

        // Optional: selected fulfillment option
        $selectedOption = $this->fulfillmentMapper->mapSelectedOption($order);
        if ($selectedOption !== null) {
            $data['fulfillment_option_id'] = $selectedOption;
        }

        // Optional: fulfillment address (if set)
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress !== null) {
            $data['fulfillment_address'] = $this->normalizeAddress($shippingAddress);
        }

        // Optional: buyer data (if customer exists with ALL required fields)
        // Spec: openapi.agentic_checkout.yaml lines 303-311
        // Buyer schema requires: email, first_name, last_name (all REQUIRED)
        // Only include buyer if ALL three required fields are present
        $customer = $order->getCustomer();
        if ($customer !== null &&
            $customer->getEmail() !== null &&
            $customer->getFirstName() !== null &&
            $customer->getLastName() !== null
        ) {
            $data['buyer'] = [
                'email' => $customer->getEmail(),
                'first_name' => $customer->getFirstName(),
                'last_name' => $customer->getLastName(),
            ];

            // Optional: phone_number
            if ($customer->getPhoneNumber() !== null) {
                $data['buyer']['phone_number'] = $customer->getPhoneNumber();
            }
        }

        // Payment provider configuration
        // Spec: openapi.agentic_checkout.yaml lines 266-268
        // Find enabled ACP payment method for this channel
        $channel = $order->getChannel();

        if (!$channel instanceof ChannelInterface) {
            throw new \LogicException('Order must have a Channel');
        }
        $acpPaymentMethods = $this->paymentMethodRepository->findEnabledForChannel($channel);
        $acpMethod = null;
        foreach ($acpPaymentMethods as $method) {
            $gatewayConfig = $method->getGatewayConfig();
            if ($gatewayConfig !== null && $gatewayConfig->getFactoryName() === PaymentGatewayFactory::ACP->value) {
                $acpMethod = $method;

                break;
            }
        }

        // TODO: Make payment provider configurable when OpenAI ACP spec supports multiple providers
        // Currently hardcoded 'stripe' because ACP spec v2025-09-29 only supports stripe as provider enum value
        // See: /spec/openapi/openapi.agentic_checkout.yaml - PaymentProvider schema defines enum: [stripe]
        $data['payment_provider'] = [
            'provider' => 'stripe',
            'supported_payment_methods' => ['card'],
        ];

        // Per ACP spec Section 4.4: Complete response MUST include order object when status is 'completed'
        // Spec: "Response MUST include status: completed and an order with id, checkout_session_id, and permalink_url"
        if ($status === 'completed') {
            $orderNumber = $order->getNumber();
            $tokenValue = $order->getTokenValue();

            if ($orderNumber === null || $tokenValue === null) {
                throw new \LogicException('Completed order must have a number and token value');
            }

            $data['order'] = [
                'id' => $orderNumber,
                'checkout_session_id' => $session->getAcpId(),
                'permalink_url' => $this->urlGenerator->generate(
                    'sylius_shop_order_show',
                    ['tokenValue' => $tokenValue],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
            ];
        }

        return $data;
    }

    /**
     * Normalizes Sylius Address to ACP format
     *
     * Spec: openapi.agentic_checkout.yaml lines 290-301
     * IMPORTANT: Uses line_one/line_two (NOT address_line_1/2), NO phone field
     *
     * @return array<string, string>
     */
    private function normalizeAddress(AddressInterface $address): array
    {
        $data = [];

        // name field (combine first + last)
        $firstName = $address->getFirstName();
        $lastName = $address->getLastName();
        if ($firstName !== null || $lastName !== null) {
            $data['name'] = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));
        }

        // line_one (required)
        $street = $address->getStreet();
        if ($street !== null) {
            // If street contains newline, split into line_one and line_two
            $lines = explode("\n", $street, 2);
            $data['line_one'] = $lines[0];
            if (isset($lines[1]) && $lines[1] !== '') {
                $data['line_two'] = $lines[1];
            }
        }

        // city (required)
        if ($address->getCity() !== null) {
            $data['city'] = $address->getCity();
        }

        // state (required by ACP spec, empty string if not set in Sylius)
        $data['state'] = $address->getProvinceCode() ?? '';

        // country (required, ISO 3166-1 alpha-2 UPPERCASE)
        if ($address->getCountryCode() !== null) {
            $data['country'] = strtoupper($address->getCountryCode());
        }

        // postal_code (required)
        if ($address->getPostcode() !== null) {
            $data['postal_code'] = $address->getPostcode();
        }

        // Note: ACP Address does NOT have phone field (spec lines 290-301)

        return $data;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ACPCheckoutSession;
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            ACPCheckoutSession::class => true,
        ];
    }
}
