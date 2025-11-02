<?php

declare(strict_types=1);

namespace Tests\Guiziweb\SyliusAgenticCommerceProtocolPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\Persistence\ObjectManager;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Enum\PaymentGatewayFactory;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Webmozart\Assert\Assert;

final class ACPContext implements Context
{
    /**
     * @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository
     * @param ExampleFactoryInterface<PaymentMethodInterface> $paymentMethodExampleFactory
     */
    public function __construct(
        private SharedStorageInterface $sharedStorage,
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private ExampleFactoryInterface $paymentMethodExampleFactory,
        private ObjectManager $paymentMethodManager,
    ) {
    }

    /**
     * @Given ACP is enabled for this channel
     */
    public function acpIsEnabledForThisChannel(): void
    {
        $this->setupACPPaymentMethod(withSignatureSecret: true);
    }

    /**
     * @Given ACP is enabled without signature secret
     */
    public function acpIsEnabledWithoutSignatureSecret(): void
    {
        $this->setupACPPaymentMethod(withSignatureSecret: false);
    }

    private function setupACPPaymentMethod(bool $withSignatureSecret): void
    {
        // Create PaymentMethod using Offline gateway (for testing)
        // We set factory_name to 'acp' so CompleteCheckoutSessionAction can identify it
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $this->paymentMethodExampleFactory->create([
            'name' => 'ACP',
            'code' => 'acp',
            'description' => 'ACP Payment Gateway',
            'gatewayName' => 'acp',
            'gatewayFactory' => 'Offline', // Use Offline provider (Sylius built-in)
            'enabled' => true,
            'channels' => $this->sharedStorage->has('channel') ? [$this->sharedStorage->get('channel')] : [],
        ]);

        $this->sharedStorage->set('payment_method', $paymentMethod);
        $this->paymentMethodRepository->add($paymentMethod);

        // Configure GatewayConfig
        /** @var GatewayConfigInterface|null $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        Assert::notNull($gatewayConfig);

        // Set factory name to 'acp' so our code can identify this payment method
        $gatewayConfig->setFactoryName(PaymentGatewayFactory::ACP->value);
        $gatewayConfig->setUsePayum(false);

        $config = [
            'stripe_secret_key' => 'sk_test_fake_key_789',
            'stripe_account_id' => 'acct_test_123',
            'webhook_url' => 'https://chatgpt.test/webhooks/acp',
            'webhook_secret' => 'test_webhook_secret_456',
            'bearer_token' => 'test_bearer_token_123',
        ];

        if ($withSignatureSecret) {
            $config['signature_secret'] = 'test_signature_secret';
        }

        $gatewayConfig->setConfig($config);

        $this->paymentMethodManager->flush();
    }
}
