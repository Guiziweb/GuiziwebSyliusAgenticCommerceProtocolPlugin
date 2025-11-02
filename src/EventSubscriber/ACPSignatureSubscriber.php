<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\EventSubscriber;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Provider\ACPGatewayConfigProvider;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Security\ACPSignatureValidator;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Security\SignatureValidationException;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Validates request signatures for ACP endpoints
 *
 * Pattern: Symfony Event Subscriber
 * Implementation: HMAC SHA256 following ShopBridge/Magento reference implementations
 */
final readonly class ACPSignatureSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ACPSignatureValidator $signatureValidator,
        private ACPGatewayConfigProvider $gatewayConfigProvider,
        private ChannelContextInterface $channelContext,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Run before authentication (priority > 8)
            KernelEvents::REQUEST => ['onKernelRequest', 9],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->attributes->get('_acp', false)) {
            return;
        }

        // Skip validation for GET requests (no body to sign)
        if ($request->getMethod() === 'GET') {
            return;
        }

        // Get signature and timestamp headers
        $signature = $request->headers->get('Signature');
        $timestamp = $request->headers->get('Timestamp');

        // If no signature header, skip validation (signature is RECOMMENDED, not REQUIRED)
        if ($signature === null || $signature === '') {
            return;
        }

        // Get signature secret from gateway config
        try {
            $channel = $this->channelContext->getChannel();
            /** @var ChannelInterface $channel */
            $config = $this->gatewayConfigProvider->getConfig($channel);
            $signatureSecret = $config['signature_secret'] ?? null;
        } catch (\Throwable) {
            // If gateway not configured, skip signature validation
            return;
        }

        // If signature secret not configured, but signature provided, reject request
        if ($signatureSecret === null || $signatureSecret === '') {
            $event->setResponse(new JsonResponse([
                'type' => 'invalid_request',
                'code' => 'signature_validation_failed',
                'message' => 'Signature verification is not configured for this merchant',
            ], Response::HTTP_UNAUTHORIZED));

            return;
        }

        // Validate signature
        try {
            $body = $request->getContent();

            $this->signatureValidator->verify(
                $signatureSecret,
                $signature,
                $body,
                $timestamp,
            );
        } catch (SignatureValidationException $e) {
            $event->setResponse(new JsonResponse([
                'type' => 'invalid_request',
                'code' => 'signature_validation_failed',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED));
        }
    }
}
