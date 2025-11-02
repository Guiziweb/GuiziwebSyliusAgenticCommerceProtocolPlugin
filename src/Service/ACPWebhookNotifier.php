<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Service;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Entity\ACPCheckoutSessionInterface;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Provider\ACPGatewayConfigProvider;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends webhook notifications to ChatGPT per ACP spec
 *
 * Spec: openapi.agentic_checkout_webhook.yaml
 * - POST to webhook_url from GatewayConfig
 * - HMAC signature in Merchant-Signature header
 * - Event types: order_create, order_update
 */
final readonly class ACPWebhookNotifier
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ACPGatewayConfigProvider $gatewayConfigProvider,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Send order webhook to ChatGPT
     *
     * @param string $eventType order_create or order_update
     * @param string $status created, manual_review, confirmed, canceled, shipped, fulfilled
     */
    public function notify(
        ACPCheckoutSessionInterface $session,
        OrderInterface $order,
        string $eventType,
        string $status = 'created',
    ): void {
        // Get webhook config from payment method's GatewayConfig
        $channel = $session->getChannel();
        if (!$channel instanceof ChannelInterface) {
            return;
        }

        try {
            $config = $this->gatewayConfigProvider->getConfig($channel);
            $webhookUrl = $config['webhook_url'] ?? null;
            $webhookSecret = $config['webhook_secret'] ?? null;
        } catch (\Throwable $e) {
            // ACP Stripe not configured - skip silently
            return;
        }

        if (!is_string($webhookUrl) || $webhookUrl === '') {
            // Webhook URL not configured - skip silently (optional feature)
            return;
        }

        if (!is_string($webhookSecret) || $webhookSecret === '') {
            throw new \InvalidArgumentException('Webhook secret is required when webhook URL is configured');
        }

        // Build webhook payload per ACP spec (openapi.agentic_checkout_webhook.yaml line 124-130)
        $payload = [
            'type' => $eventType,
            'data' => [
                'type' => 'order',
                'checkout_session_id' => $session->getAcpId(),
                'permalink_url' => $this->buildPermalinkUrl($order),
                'status' => $status,
                'refunds' => [],
            ],
        ];

        $body = json_encode($payload, \JSON_THROW_ON_ERROR);

        // Generate HMAC signature (per spec line 20-25)
        $signature = hash_hmac('sha256', $body, $webhookSecret);

        // Send webhook
        try {
            $this->httpClient->request('POST', $webhookUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Merchant-Signature' => $signature,
                    'Request-Id' => uniqid('acp_', true),
                    'Timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                ],
                'body' => $body,
                'timeout' => 10,
            ]);
        } catch (\Throwable $e) {
            // Log error but don't fail the order - webhooks are optional
            // TODO: implement retry mechanism or queue
            $this->logger->error('Failed to send ACP webhook', [
                'webhook_url' => $webhookUrl,
                'order_id' => $order->getId(),
                'order_number' => $order->getNumber(),
                'checkout_session_id' => $session->getAcpId(),
                'event_type' => $eventType,
                'status' => $status,
                'exception' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
        }
    }

    /**
     * Build permalink URL for order using Symfony router
     *
     * Generates absolute URL to Sylius shop order page
     */
    private function buildPermalinkUrl(OrderInterface $order): string
    {
        return $this->urlGenerator->generate(
            'sylius_shop_order_show',
            ['tokenValue' => $order->getTokenValue()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
