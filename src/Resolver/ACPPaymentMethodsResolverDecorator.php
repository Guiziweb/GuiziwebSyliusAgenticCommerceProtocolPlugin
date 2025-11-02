<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Resolver;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Enum\PaymentGatewayFactory;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Resolver\PaymentMethodsResolverInterface;

/**
 * Decorates the default payment methods resolver to filter out ACP payment methods
 * from the normal shop checkout flow.
 *
 * ACP payment methods should only be available via the ACP API,
 * not in the regular shop checkout UI.
 *
 * Pattern: Symfony Decorator Pattern
 */
final readonly class ACPPaymentMethodsResolverDecorator implements PaymentMethodsResolverInterface
{
    public function __construct(
        private PaymentMethodsResolverInterface $decorated,
    ) {
    }

    public function getSupportedMethods(PaymentInterface $subject): array
    {
        $methods = $this->decorated->getSupportedMethods($subject);

        // Filter out ACP payment methods from normal shop checkout
        // ACP methods are identified by gateway factory name pattern: acp or acp_*
        return array_values(array_filter($methods, function ($method) {
            $gatewayConfig = $method->getGatewayConfig();
            if ($gatewayConfig === null) {
                return true;
            }

            $factoryName = $gatewayConfig->getFactoryName();

            // Exclude any payment method with factory name 'acp' or starting with 'acp_'
            return $factoryName !== PaymentGatewayFactory::ACP->value && !str_starts_with((string) $factoryName, 'acp_');
        }));
    }

    public function supports(PaymentInterface $subject): bool
    {
        return $this->decorated->supports($subject);
    }
}
