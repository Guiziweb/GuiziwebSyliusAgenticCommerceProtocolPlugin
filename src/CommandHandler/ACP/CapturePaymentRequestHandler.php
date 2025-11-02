<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\CommandHandler\ACP;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Command\ACP\CapturePaymentRequest;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Handles ACP vault token capture via PSP
 *
 * Flow (per ACP spec + Sylius 2 pattern):
 * 1. Retrieve PaymentRequest
 * 2. Extract vault token from payload
 * 3. Charge token via PSP API (synchronous)
 * 4. Store result in Payment details
 * 5. Apply Payment transitions (complete/fail)
 * 6. Apply PaymentRequest transition (complete)
 */
#[AsMessageHandler]
final readonly class CapturePaymentRequestHandler
{
    public function __construct(
        private PaymentRequestProviderInterface $paymentRequestProvider,
        private StateMachineInterface $stateMachine,
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Charge vault token via PSP
     *
     * @param string $pspUrl PSP base URL (e.g., http://localhost:4000)
     * @param string $pspChargeEndpoint PSP charge endpoint path (e.g., /agentic_commerce/create_and_process_payment_intent)
     * @param string $merchantSecretKey Merchant secret key for authentication
     * @param string $vaultToken Vault token (vt_xxx)
     * @param int $amount Amount in cents
     * @param string $currency Currency code (lowercase)
     * @param string|null $signatureSecret Shared secret for HMAC signature
     *
     * @return object{id: string, status: string, amount: int, currency: string, created: int} Payment intent object
     *
     * @throws \RuntimeException on PSP error
     */
    private function chargeViaPSP(
        string $pspUrl,
        string $pspChargeEndpoint,
        string $merchantSecretKey,
        string $vaultToken,
        int $amount,
        string $currency,
        ?string $signatureSecret,
    ): object {
        $endpoint = rtrim($pspUrl, '/') . '/' . ltrim($pspChargeEndpoint, '/');

        $requestBody = [
            'shared_payment_token' => $vaultToken,
            'amount' => $amount,
            'currency' => $currency,
        ];

        $payload = json_encode($requestBody);
        $timestamp = (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339);

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $merchantSecretKey,
            'API-Version' => '2025-09-29',
            'Accept-Language' => 'en-US',
            'User-Agent' => 'Sylius-ACP-Plugin/1.0',
            'Idempotency-Key' => 'charge_' . time() . '_' . bin2hex(random_bytes(8)),
            'Request-Id' => 'req_' . time(),
            'Timestamp' => $timestamp,
        ];

        // Add signature if secret is configured
        if ($signatureSecret !== null && $signatureSecret !== '') {
            $payloadString = is_string($payload) ? $payload : json_encode($payload);
            if ($payloadString === false) {
                throw new \RuntimeException('Failed to encode payload to JSON');
            }
            $signature = $this->computeSignature($payloadString, $signatureSecret);
            $headers['Signature'] = $signature;
        }

        // Make HTTP request to PSP
        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => $headers,
                'body' => $payload,
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false); // false = don't throw on error status

            if ($statusCode !== 201) {
                $errorData = json_decode($content, true);
                if (!is_array($errorData)) {
                    throw new \RuntimeException(sprintf('PSP returned status %d with invalid response', $statusCode));
                }
                $errorMessage = (string) ($errorData['message'] ?? 'Unknown PSP error');

                throw new \RuntimeException(sprintf('PSP returned status %d: %s', $statusCode, $errorMessage));
            }

            /** @var object{id: string, status: string, amount: int, currency: string, created: int}|null $paymentIntent */
            $paymentIntent = json_decode($content);
            if ($paymentIntent === null || !is_object($paymentIntent)) {
                throw new \RuntimeException('Invalid JSON response from PSP');
            }

            return $paymentIntent;
        } catch (\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface $e) {
            throw new \RuntimeException(sprintf('PSP request failed: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * Compute HMAC SHA256 signature for request payload
     *
     * @param string $payload JSON request body
     * @param string $secret Shared secret
     *
     * @return string Base64url encoded signature
     */
    private function computeSignature(string $payload, string $secret): string
    {
        $hmac = hash_hmac('sha256', $payload, $secret, true);

        // Convert to base64url (RFC 4648 Section 5)
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($hmac));
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

        // Extract delegated token from payload
        if (!isset($payload['token']) || !is_string($payload['token'])) {
            throw new \InvalidArgumentException('Payment token not found in payload');
        }

        $token = $payload['token'];

        // Get PSP credentials from GatewayConfig
        $gatewayConfig = $paymentRequest->getMethod()->getGatewayConfig();
        if ($gatewayConfig === null) {
            throw new \LogicException('GatewayConfig not found for payment method');
        }

        $config = $gatewayConfig->getConfig();
        $pspUrl = $config['psp_url'] ?? null;
        $pspMerchantSecretKey = $config['psp_merchant_secret_key'] ?? null;
        $pspChargeEndpoint = $config['psp_charge_endpoint'] ?? null;

        if (!is_string($pspUrl) || $pspUrl === '') {
            throw new \InvalidArgumentException('PSP URL not configured');
        }

        if (!is_string($pspMerchantSecretKey) || $pspMerchantSecretKey === '') {
            throw new \InvalidArgumentException('PSP merchant secret key not configured');
        }

        if (!is_string($pspChargeEndpoint) || $pspChargeEndpoint === '') {
            throw new \InvalidArgumentException('PSP charge endpoint not configured');
        }

        $amount = $payment->getAmount();
        $currencyCode = $payment->getCurrencyCode();
        if ($amount === null) {
            throw new \InvalidArgumentException('Payment amount cannot be null');
        }
        if ($currencyCode === null) {
            throw new \InvalidArgumentException('Payment currency code cannot be null');
        }

        $signatureSecret = $config['signature_secret'] ?? null;
        if ($signatureSecret !== null && !is_string($signatureSecret)) {
            throw new \InvalidArgumentException('Signature secret must be a string');
        }

        // Charge vault token via PSP (ACP spec compliant)
        try {
            $charge = $this->chargeViaPSP(
                $pspUrl,
                $pspChargeEndpoint,
                $pspMerchantSecretKey,
                $token,
                $amount,
                strtolower($currencyCode),
                $signatureSecret,
            );
        } catch (\Exception $e) {
            // Handle PSP errors
            $payment->setDetails([
                'error' => [
                    'type' => 'psp_error',
                    'code' => 'charge_failed',
                    'message' => $e->getMessage(),
                ],
                'acp_token' => $token,
            ]);

            throw new \RuntimeException(sprintf('Failed to charge vault token via PSP: %s', $e->getMessage()), 0, $e);
        }

        // Store charge details in Payment
        $payment->setDetails([
            'psp_payment_intent_id' => $charge->id ?? null,
            'status' => $charge->status ?? null,
            'amount' => $charge->amount,
            'currency' => $charge->currency,
            'created' => $charge->created,
            'vault_token' => $token,
        ]);

        // Apply Payment transitions: cart -> new -> processing -> completed
        // First, move from cart to new if needed
        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CREATE)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CREATE);
        }

        // Then process the payment
        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_PROCESS)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_PROCESS);
        }

        // Finally complete it
        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE);
        }

        // Set PaymentRequest response data (for synchronous response)
        $paymentRequest->setResponseData([
            'payment_intent_id' => $charge->id,
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
