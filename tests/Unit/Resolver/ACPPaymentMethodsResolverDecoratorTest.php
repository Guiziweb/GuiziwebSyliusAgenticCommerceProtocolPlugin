<?php

declare(strict_types=1);

namespace Tests\Guiziweb\SyliusAgenticCommerceProtocolPlugin\Unit\Resolver;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Resolver\ACPPaymentMethodsResolverDecorator;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Resolver\PaymentMethodsResolverInterface;

final class ACPPaymentMethodsResolverDecoratorTest extends TestCase
{
    private PaymentMethodsResolverInterface $decorated;
    private ACPPaymentMethodsResolverDecorator $decorator;

    protected function setUp(): void
    {
        $this->decorated = $this->createMock(PaymentMethodsResolverInterface::class);
        $this->decorator = new ACPPaymentMethodsResolverDecorator($this->decorated);
    }

    /** @test */
    public function it_filters_out_acp_payment_methods(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $normalMethod = $this->createPaymentMethod('stripe', 'stripe');
        $acpMethod = $this->createPaymentMethod('acp', 'acp');
        $offlineMethod = $this->createPaymentMethod('cash_on_delivery', 'offline');

        $this->decorated
            ->expects($this->once())
            ->method('getSupportedMethods')
            ->with($payment)
            ->willReturn([$normalMethod, $acpMethod, $offlineMethod]);

        $result = $this->decorator->getSupportedMethods($payment);

        $this->assertCount(2, $result);
        $this->assertContains($normalMethod, $result);
        $this->assertContains($offlineMethod, $result);
        $this->assertNotContains($acpMethod, $result);
    }

    /** @test */
    public function it_keeps_all_methods_when_no_acp_methods_present(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $stripeMethod = $this->createPaymentMethod('stripe', 'stripe');
        $offlineMethod = $this->createPaymentMethod('cash_on_delivery', 'offline');

        $this->decorated
            ->expects($this->once())
            ->method('getSupportedMethods')
            ->with($payment)
            ->willReturn([$stripeMethod, $offlineMethod]);

        $result = $this->decorator->getSupportedMethods($payment);

        $this->assertCount(2, $result);
        $this->assertContains($stripeMethod, $result);
        $this->assertContains($offlineMethod, $result);
    }

    /** @test */
    public function it_handles_payment_methods_without_gateway_config(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $methodWithoutConfig = $this->createMock(PaymentMethodInterface::class);
        $methodWithoutConfig->method('getGatewayConfig')->willReturn(null);

        $this->decorated
            ->expects($this->once())
            ->method('getSupportedMethods')
            ->with($payment)
            ->willReturn([$methodWithoutConfig]);

        $result = $this->decorator->getSupportedMethods($payment);

        $this->assertCount(1, $result);
        $this->assertContains($methodWithoutConfig, $result);
    }

    /** @test */
    public function it_delegates_supports_to_decorated(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->decorated
            ->expects($this->once())
            ->method('supports')
            ->with($payment)
            ->willReturn(true);

        $result = $this->decorator->supports($payment);

        $this->assertTrue($result);
    }

    private function createPaymentMethod(string $code, string $factoryName): PaymentMethodInterface
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn($factoryName);

        $method = $this->createMock(PaymentMethodInterface::class);
        $method->method('getCode')->willReturn($code);
        $method->method('getGatewayConfig')->willReturn($gatewayConfig);

        return $method;
    }
}