<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\EventSubscriber;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Enum\PaymentGatewayFactory;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Provider\ACPGatewayConfigProvider;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Authenticates ACP API requests using Bearer token and validates API version
 *
 * Per ACP spec (rfc.agentic_checkout.md):
 * - Client MUST send API-Version header
 * - Server MUST validate support (e.g., 2025-09-29)
 * - All endpoints require Authorization: Bearer <token>
 * - Token is configured in payment method gateway config as 'bearer_token'
 *
 * Pattern: Symfony Event Subscriber
 */
final readonly class ACPAuthenticationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ChannelContextInterface $channelContext,
        private ACPGatewayConfigProvider $gatewayConfigProvider,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Priority 16: After routing (32) but before controller execution (0)
            // This ensures _acp route attribute is available
            KernelEvents::REQUEST => ['authenticate', 16],
        ];
    }

    public function authenticate(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Only authenticate ACP API routes (identified by _acp route attribute)
        if (!$request->attributes->get('_acp', false)) {
            return;
        }

        // 1. Validate API-Version header (REQUIRED per spec)
        $apiVersion = $request->headers->get('API-Version');
        if ($apiVersion === null || $apiVersion === '') {
            $this->badRequest($event, 'missing_api_version', 'API-Version header is required');

            return;
        }

        if ($apiVersion !== PaymentGatewayFactory::SUPPORTED_API_VERSION) {
            $this->badRequest(
                $event,
                'unsupported_api_version',
                sprintf(
                    'API version "%s" is not supported. Supported version: %s',
                    $apiVersion,
                    PaymentGatewayFactory::SUPPORTED_API_VERSION,
                ),
            );

            return;
        }

        if (in_array($request->getMethod(), [Request::METHOD_POST, Request::METHOD_PUT, Request::METHOD_PATCH], true)) {
            $contentType = $request->headers->get('Content-Type');
            $hasBody = $request->getContent() !== '';

            // If request has a body, Content-Type MUST be application/json
            if ($hasBody && !str_starts_with($contentType ?? '', 'application/json')) {
                $this->badRequest(
                    $event,
                    'invalid_content_type',
                    'Content-Type must be application/json for requests with body',
                );

                return;
            }
        }

        // 3. Extract Bearer token from Authorization header
        $authHeader = $request->headers->get('Authorization');
        if ($authHeader === null || $authHeader === '') {
            $this->unauthorized($event, 'Missing Authorization header');

            return;
        }

        if (!str_starts_with($authHeader, 'Bearer ')) {
            $this->unauthorized($event, 'Invalid Authorization header format. Expected: Bearer <token>');

            return;
        }

        $providedToken = substr($authHeader, 7); // Remove 'Bearer ' prefix

        // Get configured bearer token from ACP Stripe gateway config
        try {
            $channel = $this->channelContext->getChannel();
            /** @var ChannelInterface $channel */
            $expectedToken = $this->gatewayConfigProvider->getConfigValue($channel, 'bearer_token');
        } catch (\Throwable $e) {
            $this->unauthorized($event, 'ACP Stripe payment method not configured');

            return;
        }

        // Compare tokens (constant-time comparison to prevent timing attacks)
        if (!hash_equals($expectedToken, $providedToken)) {
            $this->unauthorized($event, 'Invalid bearer token');

            return;
        }

        // Authentication successful - continue to controller
    }

    /**
     * Return 400 Bad Request response (ACP error format)
     *
     * @param string|null $param RFC 9535 JSONPath to the problematic parameter
     */
    private function badRequest(RequestEvent $event, string $code, string $message, ?string $param = null): void
    {
        $data = [
            'type' => 'invalid_request',
            'code' => $code,
            'message' => $message,
        ];

        if ($param !== null) {
            $data['param'] = $param;
        }

        $response = new JsonResponse($data, Response::HTTP_BAD_REQUEST);

        $event->setResponse($response);
    }

    /**
     * Return 401 Unauthorized response
     */
    private function unauthorized(RequestEvent $event, string $message): void
    {
        $response = new JsonResponse(
            [
                'error' => 'unauthorized',
                'message' => $message,
            ],
            Response::HTTP_UNAUTHORIZED,
        );

        $event->setResponse($response);
    }
}
