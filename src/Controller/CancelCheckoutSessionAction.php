<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Enum\ACPStatus;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Repository\ACPCheckoutSessionRepositoryInterface;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Serializer\Normalizer\ACPCheckoutSessionNormalizer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * POST /checkout_sessions/{checkout_session_id}/cancel
 *
 * Cancels a session if not already completed or canceled.
 * ACP Spec: openapi.agentic_checkout.yaml operation "cancelCheckoutSession"
 */
final readonly class CancelCheckoutSessionAction
{
    public function __construct(
        private ACPCheckoutSessionRepositoryInterface $acpCheckoutSessionRepository,
        private ACPCheckoutSessionNormalizer $normalizer,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(string $checkoutSessionId): JsonResponse
    {
        // Find session
        $session = $this->acpCheckoutSessionRepository->findOneByAcpId($checkoutSessionId);
        if ($session === null) {
            throw new NotFoundHttpException(sprintf('Checkout session "%s" not found', $checkoutSessionId));
        }

        // Check if session can be canceled
        $currentStatus = $session->getStatus();
        if (in_array($currentStatus, [ACPStatus::COMPLETED->value, ACPStatus::CANCELED->value], true)) {
            throw new HttpException(
                Response::HTTP_METHOD_NOT_ALLOWED,
                sprintf('Cannot cancel session with status "%s"', $currentStatus),
            );
        }

        // Cancel the session (set explicit status)
        // Note: The underlying Order stays in state='cart' (Sylius just deletes abandoned carts)
        // The normalizer will respect this explicit 'canceled' status instead of recalculating
        $session->setStatus(ACPStatus::CANCELED->value);

        $this->entityManager->flush();

        // Normalize to ACP format
        $responseData = $this->normalizer->normalize($session);

        return new JsonResponse($responseData, Response::HTTP_OK);
    }
}
