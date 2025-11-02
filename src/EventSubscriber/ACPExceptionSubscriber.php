<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\EventSubscriber;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Exception\ACPValidationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Formats exceptions on ACP routes to match ACP error response spec
 *
 * Per ACP spec (openapi.agentic_checkout.yaml lines 572-578):
 * Error responses MUST include:
 * - type: error type (e.g., 'invalid_request', 'not_found')
 * - code: implementation-defined error code
 * - message: human-readable error message
 * - param: RFC 9535 JSONPath (optional)
 */
final readonly class ACPExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            // Priority -10: After security/firewall but before Symfony's default exception listener (priority -128)
            KernelEvents::EXCEPTION => ['onKernelException', -10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        // Only format exceptions for ACP API routes
        if (!$request->attributes->get('_acp', false)) {
            return;
        }

        $exception = $event->getThrowable();

        // Format ACPValidationException with param field
        if ($exception instanceof ACPValidationException) {
            $data = [
                'type' => 'invalid_request',
                'code' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
            ];

            if ($exception->getParam() !== null) {
                $data['param'] = $exception->getParam();
            }

            $event->setResponse(new JsonResponse($data, Response::HTTP_BAD_REQUEST));

            return;
        }

        // Format NotFoundHttpException (404)
        if ($exception instanceof NotFoundHttpException) {
            $event->setResponse(new JsonResponse([
                'type' => 'not_found',
                'code' => 'resource_not_found',
                'message' => $exception->getMessage(),
            ], Response::HTTP_NOT_FOUND));

            return;
        }

        // Format generic BadRequestHttpException (400)
        if ($exception instanceof BadRequestHttpException) {
            $event->setResponse(new JsonResponse([
                'type' => 'invalid_request',
                'code' => 'invalid_request',
                'message' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST));

            return;
        }

        // Format other HTTP exceptions
        if ($exception instanceof HttpExceptionInterface) {
            $event->setResponse(new JsonResponse([
                'type' => 'api_error',
                'code' => 'http_error',
                'message' => $exception->getMessage(),
            ], $exception->getStatusCode()));

            return;
        }

        // Generic 500 error for unexpected exceptions
        $event->setResponse(new JsonResponse([
            'type' => 'api_error',
            'code' => 'internal_error',
            'message' => 'An internal error occurred',
        ], Response::HTTP_INTERNAL_SERVER_ERROR));
    }
}
