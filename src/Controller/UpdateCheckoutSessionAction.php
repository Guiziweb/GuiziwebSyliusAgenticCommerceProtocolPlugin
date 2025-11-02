<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Applier\ACPBuyerApplier;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Applier\ACPOrderApplier;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Exception\ACPValidationException;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Mapper\ACPFulfillmentMapperInterface;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Repository\ACPCheckoutSessionRepositoryInterface;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Resolver\ACPStatusResolverInterface;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Serializer\Normalizer\ACPCheckoutSessionNormalizer;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * POST /checkout_sessions/{checkout_session_id}
 *
 * Applies changes (items, fulfillment address, fulfillment option) and returns updated state.
 * ACP Spec: openapi.agentic_checkout.yaml operation "updateCheckoutSession"
 */
final readonly class UpdateCheckoutSessionAction
{
    public function __construct(
        private ACPCheckoutSessionRepositoryInterface $acpCheckoutSessionRepository,
        private ACPOrderApplier $orderApplier,
        private ACPBuyerApplier $buyerApplier,
        private OrderProcessorInterface $orderProcessor,
        private ACPStatusResolverInterface $statusResolver,
        private ACPCheckoutSessionNormalizer $normalizer,
        private EntityManagerInterface $entityManager,
        private StateMachineInterface $stateMachine,
        private ACPFulfillmentMapperInterface $fulfillmentMapper,
    ) {
    }

    public function __invoke(string $checkoutSessionId, Request $request): JsonResponse
    {
        // Find session
        $session = $this->acpCheckoutSessionRepository->findOneByAcpId($checkoutSessionId);
        if ($session === null) {
            throw new NotFoundHttpException(sprintf('Checkout session "%s" not found', $checkoutSessionId));
        }

        $order = $session->getOrder();
        if (!$order instanceof OrderInterface) {
            throw new \LogicException('Checkout session must have an associated order');
        }

        // Get request body
        $content = $request->getContent();
        if ($content === '') {
            throw new BadRequestHttpException('Request body is required');
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new BadRequestHttpException('Invalid JSON');
        }

        // Validate fulfillment_option_id if provided
        // Per ACP RFC: "fulfillment_option_id MUST match an element of fulfillment_options when set"
        if (isset($data['fulfillment_option_id']) && is_string($data['fulfillment_option_id'])) {
            $fulfillmentOptions = $this->fulfillmentMapper->mapOptions($order);
            $validIds = array_column($fulfillmentOptions, 'id');

            if (!in_array($data['fulfillment_option_id'], $validIds, true)) {
                throw new ACPValidationException(
                    sprintf('Invalid fulfillment_option_id: "%s" not found in available options', $data['fulfillment_option_id']),
                    'invalid_parameter',
                    '$.fulfillment_option_id',
                );
            }
        }

        // Apply updates to order
        $this->orderApplier->apply($data, $order);

        // Apply buyer data if provided
        if (isset($data['buyer']) && is_array($data['buyer'])) {
            $this->buyerApplier->apply($data['buyer'], $order);
        }

        // Reprocess order (recalculate taxes, shipping, promotions)
        $this->orderProcessor->process($order);

        // Apply checkout state transitions (pattern: Sylius OrderAddressModifier)
        // When address is added, transition to 'addressed'
        if ($order->getShippingAddress() !== null) {
            if ($this->stateMachine->can($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_ADDRESS)) {
                $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_ADDRESS);
            }
        }

        // If shipping method is selected, transition to 'shipping_selected'
        if (!$order->getShipments()->isEmpty()) {
            foreach ($order->getShipments() as $shipment) {
                if ($shipment->getMethod() !== null) {
                    if ($this->stateMachine->can($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING)) {
                        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING);

                        break; // Only apply once
                    }
                }
            }
        }

        // Update session status
        $session->setStatus($this->statusResolver->resolve($order));

        // Persist changes
        $this->entityManager->flush();

        // Normalize to ACP format
        $responseData = $this->normalizer->normalize($session);

        return new JsonResponse($responseData, Response::HTTP_OK);
    }
}
