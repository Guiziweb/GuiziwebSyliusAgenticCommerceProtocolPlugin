<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Provider;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Enum\PaymentGatewayFactory;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;

/**
 * Provides ACP gateway configuration for a channel
 *
 * Centralizes the logic to find and retrieve gateway config to avoid duplication
 */
final readonly class ACPGatewayConfigProvider
{
    /**
     * @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository
     */
    public function __construct(
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
    ) {
    }

    /**
     * Get full ACP gateway configuration for a channel
     *
     * @return array<string, mixed>
     *
     * @throws \LogicException if ACP payment method not found or not configured
     */
    public function getConfig(ChannelInterface $channel): array
    {
        $paymentMethod = $this->findACPPaymentMethod($channel);

        if ($paymentMethod === null) {
            throw new \LogicException('ACP payment method not found for this channel');
        }

        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if ($gatewayConfig === null) {
            throw new \LogicException('ACP payment method has no gateway configuration');
        }

        return $gatewayConfig->getConfig();
    }

    /**
     * Get a specific config value from ACP gateway
     *
     * @throws \LogicException if config not found or key not set
     */
    public function getConfigValue(ChannelInterface $channel, string $key): string
    {
        $config = $this->getConfig($channel);

        if (!isset($config[$key])) {
            throw new \LogicException(sprintf('Configuration key "%s" not found in ACP gateway config', $key));
        }

        $value = $config[$key];
        if (!is_string($value) || $value === '') {
            throw new \LogicException(sprintf('Configuration key "%s" is empty or invalid', $key));
        }

        return $value;
    }

    /**
     * Find ACP payment method for a channel using repository
     * (Using repository instead of $channel->getPaymentMethods() to ensure data is loaded in test environments)
     */
    private function findACPPaymentMethod(ChannelInterface $channel): ?PaymentMethodInterface
    {
        $paymentMethods = $this->paymentMethodRepository->findEnabledForChannel($channel);

        foreach ($paymentMethods as $method) {
            $gatewayConfig = $method->getGatewayConfig();
            if ($gatewayConfig !== null && $gatewayConfig->getFactoryName() === PaymentGatewayFactory::ACP->value) {
                return $method;
            }
        }

        return null;
    }
}
