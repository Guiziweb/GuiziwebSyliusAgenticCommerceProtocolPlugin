<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds ACP-required response headers
 *
 * Per rfc.agentic_checkout.md:
 * - Idempotency-Key — echo if provided
 * - Request-Id — echo if provided
 *
 * Pattern: Symfony Event Subscriber
 */
final class ACPResponseHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if (!$request->attributes->get('_acp', false)) {
            return;
        }

        // Echo Request-Id header if provided
        $requestId = $request->headers->get('Request-Id');
        if ($requestId !== null && $requestId !== '') {
            $response->headers->set('Request-Id', $requestId);
        }

        // Echo Idempotency-Key header if provided
        $idempotencyKey = $request->headers->get('Idempotency-Key');
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $response->headers->set('Idempotency-Key', $idempotencyKey);
        }
    }
}
