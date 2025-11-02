<?php

declare(strict_types=1);

namespace Tests\Guiziweb\SyliusAgenticCommerceProtocolPlugin\CommandHandler\ACP;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Command\ACP\CapturePaymentRequest;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Fake handler for testing - simulates successful Stripe payment without API calls
 */
#[AsMessageHandler]
final readonly class FakeCapturePaymentRequestHandler
{
    public function __construct(
        private PaymentRequestProviderInterface $paymentRequestProvider,
        private StateMachineInterface $stateMachine,
    ) {
    }

    public function __invoke(CapturePaymentRequest $command): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($command);

        // Prevent duplicate processing
        if ($paymentRequest->getState() === PaymentRequestInterface::STATE_PROCESSING) {
            return;
        }

        $payment = $paymentRequest->getPayment();
        $payload = $paymentRequest->getPayload();

        // Extract delegated token from payload (validation only)
        if (!isset($payload['token']) || !is_string($payload['token'])) {
            throw new \InvalidArgumentException('Payment token not found in payload');
        }

        // Simulate successful payment processing
        // In tests, we don't call real Stripe API
        $fakeChargeId = 'ch_test_' . bin2hex(random_bytes(12));

        // Store fake charge details in Payment
        $payment->setDetails([
            'acp_charge_id' => $fakeChargeId,
            'acp_token' => $payload['token'],
            'status' => 'succeeded',
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrencyCode(),
        ]);

        // Apply Payment transitions: cart -> new -> processing -> completed
        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CREATE)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CREATE);
        }

        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_PROCESS)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_PROCESS);
        }

        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE);
        }

        // Set PaymentRequest response data (for synchronous response)
        $paymentRequest->setResponseData([
            'charge_id' => $fakeChargeId,
            'status' => 'completed',
        ]);

        // Apply PaymentRequest transition: new -> processing -> completed
        if ($this->stateMachine->can($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_PROCESS)) {
            $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_PROCESS);
        }

        if ($this->stateMachine->can($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE)) {
            $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);
        }
    }
}