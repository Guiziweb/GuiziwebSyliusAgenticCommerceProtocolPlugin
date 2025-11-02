<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Factory;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Applier\ACPOrderApplier;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Entity\ACPCheckoutSession;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Resolver\ACPStatusResolver;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Resource\Generator\RandomnessGeneratorInterface;

/**
 * Creates ACPCheckoutSession entities
 *
 * Responsibilities:
 * - Create new ACP checkout session from ACP data
 * - Link session to Sylius Order (state=cart)
 * - Apply ACP data to order
 * - Process order (taxes, shipping, etc.)
 *
 * Pattern: Dedicated Factory service (Sylius pattern)
 */
final readonly class ACPCheckoutSessionFactory
{
    public function __construct(
        private FactoryInterface $orderFactory,
        private ACPOrderApplier $acpOrderApplier,
        private ACPStatusResolver $statusResolver,
        private OrderProcessorInterface $orderProcessor,
        private RandomnessGeneratorInterface $generator,
    ) {
    }

    /**
     * Creates a new ACP checkout session
     *
     * @param array<string, mixed> $acpData ACP request data (items, fulfillment_address, etc.)
     * @param ChannelInterface $channel Sylius channel
     *
     * @return ACPCheckoutSession Created session
     */
    public function create(
        array $acpData,
        ChannelInterface $channel,
    ): ACPCheckoutSession {
        // 1. Create new Sylius Order (state=cart)
        /** @var OrderInterface $order */
        $order = $this->orderFactory->createNew();
        $order->setChannel($channel);

        // Currency and locale are required - fail if not configured
        $currencyCode = $channel->getBaseCurrency()?->getCode();
        if ($currencyCode === null) {
            throw new \LogicException('Channel must have a base currency configured');
        }
        $order->setCurrencyCode($currencyCode);

        $localeCode = $channel->getDefaultLocale()?->getCode();
        if ($localeCode === null) {
            throw new \LogicException('Channel must have a default locale configured');
        }
        $order->setLocaleCode($localeCode);

        // Generate order token (pattern: Sylius PickupCartHandler)
        $order->setTokenValue($this->generator->generateUriSafeString(64));

        // 2. Apply ACP data to order (items, address, shipping)
        $this->acpOrderApplier->apply($acpData, $order);

        // 3. Process order (calculate taxes, shipping, promotions, etc.)
        // Pattern: Composite OrderProcessor chain
        $this->orderProcessor->process($order);

        // 4. Create ACP session entity
        $session = new ACPCheckoutSession();
        $session->setAcpId($this->generateACPId());
        $session->setOrder($order);
        $session->setChannel($channel);
        $session->setStatus($this->statusResolver->resolve($order));

        // 5. Set idempotency key if provided
        if (isset($acpData['idempotency_key']) && is_string($acpData['idempotency_key'])) {
            $session->setIdempotencyKey($acpData['idempotency_key']);
        }

        return $session;
    }

    /**
     * Generates a unique ACP session ID
     *
     * Format: acp_sess_{timestamp}_{random}
     *
     * @return string ACP session ID
     */
    private function generateACPId(): string
    {
        return sprintf(
            'acp_sess_%s_%s',
            time(),
            bin2hex(random_bytes(8)),
        );
    }
}
