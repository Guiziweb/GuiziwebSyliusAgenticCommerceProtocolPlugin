<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Applier\ACPBuyerApplier;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Exception\ACPValidationException;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Factory\ACPCheckoutSessionFactory;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Repository\ACPCheckoutSessionRepositoryInterface;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Serializer\Normalizer\ACPCheckoutSessionNormalizer;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Webmozart\Assert\Assert;

/**
 * POST /checkout_sessions
 *
 * Initializes a new checkout session from items and (optionally) buyer and fulfillment info.
 * ACP Spec: openapi.agentic_checkout.yaml operation "createCheckoutSession"
 */
final readonly class CreateCheckoutSessionAction
{
    public function __construct(
        private ChannelContextInterface $channelContext,
        private ACPCheckoutSessionFactory $checkoutSessionFactory,
        private ACPCheckoutSessionRepositoryInterface $checkoutSessionRepository,
        private ACPBuyerApplier $buyerApplier,
        private ACPCheckoutSessionNormalizer $normalizer,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        // Get request body
        $content = $request->getContent();
        if ($content === '') {
            throw new BadRequestHttpException('Request body is required');
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new BadRequestHttpException('Invalid JSON');
        }

        // Validate required fields
        if (!isset($data['items']) || !is_array($data['items']) || count($data['items']) === 0) {
            throw new ACPValidationException('At least one item is required', 'missing_parameter', '$.items');
        }

        // Get current channel
        $channel = $this->channelContext->getChannel();
        Assert::isInstanceOf($channel, ChannelInterface::class);

        // Idempotency: Check if request with same Idempotency-Key already exists
        // Per ACP RFC Section 6: Replays with same key MUST return original result or 409 Conflict
        $idempotencyKey = $request->headers->get('Idempotency-Key');
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existingSession = $this->checkoutSessionRepository->findOneByIdempotencyKey($idempotencyKey);

            if ($existingSession !== null) {
                // Calculate hash of current request body
                $currentBodyHash = hash('sha256', $content);

                // Compare with stored hash from original request
                if ($currentBodyHash !== $existingSession->getLastRequestHash()) {
                    // Same Idempotency-Key but DIFFERENT parameters → 409 Conflict
                    return new JsonResponse([
                        'type' => 'invalid_request',
                        'code' => 'idempotency_conflict',
                        'message' => 'Same Idempotency-Key used with different parameters',
                    ], Response::HTTP_CONFLICT);
                }

                // Same Idempotency-Key with SAME parameters → return cached result
                $responseData = $this->normalizer->normalize($existingSession);

                return new JsonResponse($responseData, Response::HTTP_CREATED);
            }
        }

        // Create checkout session (creates Order + applies items/address/shipping)
        $session = $this->checkoutSessionFactory->create($data, $channel);

        // Apply buyer data if provided
        if (isset($data['buyer']) && is_array($data['buyer'])) {
            $order = $session->getOrder();
            if ($order instanceof OrderInterface) {
                $this->buyerApplier->apply($data['buyer'], $order);
            }
        }

        // Store idempotency key and request body hash for future replay detection
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $session->setIdempotencyKey($idempotencyKey);
            $session->setLastRequestHash(hash('sha256', $content));
        }

        // Persist session
        $this->entityManager->persist($session);
        $this->entityManager->flush();

        // Normalize to ACP format
        $responseData = $this->normalizer->normalize($session);

        // Return 201 Created
        // Note: Idempotency-Key and Request-Id headers are automatically echoed by ACPResponseHeadersSubscriber
        return new JsonResponse($responseData, Response::HTTP_CREATED);
    }
}
