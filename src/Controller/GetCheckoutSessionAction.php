<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Controller;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Repository\ACPCheckoutSessionRepositoryInterface;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Serializer\Normalizer\ACPCheckoutSessionNormalizer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * GET /checkout_sessions/{checkout_session_id}
 *
 * Returns the latest authoritative state for the checkout session.
 * ACP Spec: openapi.agentic_checkout.yaml operation "getCheckoutSession"
 */
final readonly class GetCheckoutSessionAction
{
    public function __construct(
        private ACPCheckoutSessionRepositoryInterface $acpCheckoutSessionRepository,
        private ACPCheckoutSessionNormalizer $normalizer,
    ) {
    }

    public function __invoke(string $checkoutSessionId): JsonResponse
    {
        // Find session by ACP ID
        $session = $this->acpCheckoutSessionRepository->findOneByAcpId($checkoutSessionId);

        if ($session === null) {
            throw new NotFoundHttpException(sprintf('Checkout session "%s" not found', $checkoutSessionId));
        }

        // Normalize to ACP format
        $data = $this->normalizer->normalize($session);

        return new JsonResponse($data, Response::HTTP_OK);
    }
}
