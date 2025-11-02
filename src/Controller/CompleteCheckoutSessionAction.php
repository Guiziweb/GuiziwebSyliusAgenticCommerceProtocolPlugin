<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Enum\ACPStatus;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Enum\PaymentGatewayFactory;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Event\ACPOrderCompleted;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Exception\ACPValidationException;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Repository\ACPCheckoutSessionRepositoryInterface;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Serializer\Normalizer\ACPCheckoutSessionNormalizer;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Bundle\PaymentBundle\Announcer\PaymentRequestAnnouncerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Payment\Factory\PaymentFactoryInterface;
use Sylius\Component\Payment\Factory\PaymentRequestFactoryInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * POST /checkout_sessions/{checkout_session_id}/complete
 *
 * Finalizes the checkout by applying a payment method.
 * Creates an order and returns completed state on success.
 * ACP Spec: openapi.agentic_checkout.yaml operation "completeCheckoutSession"
 */
final readonly class CompleteCheckoutSessionAction
{
    /**
     * @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository
     * @param PaymentFactoryInterface<PaymentInterface> $paymentFactory
     * @param PaymentRequestFactoryInterface<PaymentRequestInterface> $paymentRequestFactory
     */
    public function __construct(
        private ACPCheckoutSessionRepositoryInterface $acpCheckoutSessionRepository,
        private ACPCheckoutSessionNormalizer $normalizer,
        private EntityManagerInterface $entityManager,
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private PaymentFactoryInterface $paymentFactory,
        private PaymentRequestFactoryInterface $paymentRequestFactory,
        private PaymentRequestAnnouncerInterface $paymentRequestAnnouncer,
        private StateMachineInterface $stateMachine,
        private MessageBusInterface $eventBus,
        private OrderNumberAssignerInterface $orderNumberAssigner,
    ) {
    }

    public function __invoke(string $checkoutSessionId, Request $request): JsonResponse
    {
        // Find session
        $session = $this->acpCheckoutSessionRepository->findOneByAcpId($checkoutSessionId);
        if ($session === null) {
            throw new NotFoundHttpException(sprintf('Checkout session "%s" not found', $checkoutSessionId));
        }

        // Get request body (contains payment_method_id)
        $content = $request->getContent();
        if ($content === '') {
            throw new BadRequestHttpException('Request body is required');
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new BadRequestHttpException('Invalid JSON');
        }

        // Validate payment_data per ACP spec (openapi.agentic_checkout.yaml line 557)
        if (!isset($data['payment_data']) || !is_array($data['payment_data'])) {
            throw new ACPValidationException('payment_data is required', 'missing_parameter', '$.payment_data');
        }

        $paymentData = $data['payment_data'];

        // Validate token (required per spec line 448)
        if (!isset($paymentData['token']) || !is_string($paymentData['token'])) {
            throw new ACPValidationException('payment_data.token is required', 'missing_parameter', '$.payment_data.token');
        }

        // Validate provider (required per spec line 449)
        if (!isset($paymentData['provider']) || !is_string($paymentData['provider'])) {
            throw new ACPValidationException('payment_data.provider is required', 'missing_parameter', '$.payment_data.provider');
        }

        $order = $session->getOrder();
        if (!$order instanceof OrderInterface) {
            throw new \LogicException('ACPCheckoutSession must have an Order');
        }

        $channel = $session->getChannel();
        if (!$channel instanceof ChannelInterface) {
            throw new \LogicException('ACPCheckoutSession must have a Channel');
        }

        $paymentMethods = $this->paymentMethodRepository->findEnabledForChannel($channel);
        $acpPaymentMethod = null;

        foreach ($paymentMethods as $method) {
            $gatewayConfig = $method->getGatewayConfig();
            if ($gatewayConfig !== null && $gatewayConfig->getFactoryName() === PaymentGatewayFactory::ACP->value) {
                $acpPaymentMethod = $method;

                break;
            }
        }

        if ($acpPaymentMethod === null) {
            throw new BadRequestHttpException('ACP payment method not configured for this channel');
        }

        // 2. Get or create Payment entity
        $payment = $order->getLastPayment();

        if (!$payment instanceof PaymentInterface) {
            // No payment exists, create one
            $currencyCode = $order->getCurrencyCode();
            if ($currencyCode === null) {
                throw new \LogicException('Order must have a currency code');
            }

            $payment = $this->paymentFactory->createWithAmountAndCurrencyCode(
                $order->getTotal(),
                $currencyCode,
            );
            $order->addPayment($payment);
        }

        // Set ACP payment method (may replace default method)
        $payment->setMethod($acpPaymentMethod);

        // 2bis. Transition to 'payment_selected' (pattern: Sylius ChoosePaymentMethodHandler)
        if ($this->stateMachine->can($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT)) {
            $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);
        }

        // 3. Create PaymentRequest with token in payload
        /** @var PaymentRequestInterface $paymentRequest */
        $paymentRequest = $this->paymentRequestFactory->create($payment, $acpPaymentMethod);
        $paymentRequest->setAction(PaymentRequestInterface::ACTION_CAPTURE);
        $paymentRequest->setPayload([
            'token' => $paymentData['token'],
            'provider' => $paymentData['provider'],
        ]);

        $this->entityManager->persist($paymentRequest);
        $this->entityManager->flush();

        // 4. Dispatch PaymentRequest command (synchronous execution)
        try {
            $this->paymentRequestAnnouncer->dispatchPaymentRequestCommand($paymentRequest);
        } catch (\RuntimeException $e) {
            // Payment failed - return error per ACP spec
            throw new BadRequestHttpException(
                sprintf('Payment failed: %s', $e->getMessage()),
                $e,
            );
        }

        // 5. Verify payment completed successfully
        if ($payment->getState() !== PaymentInterface::STATE_COMPLETED) {
            throw new BadRequestHttpException('Payment was not completed successfully');
        }

        // 6. Transition order state from 'cart' to 'new' (complete checkout)
        if ($this->stateMachine->can($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE)) {
            $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);
        }

        // 6bis. Assign order number (required for ACP spec order object)
        $this->orderNumberAssigner->assignNumber($order);

        // 7. Update session status to 'completed'
        $session->setStatus(ACPStatus::COMPLETED->value);
        $this->entityManager->flush();

        // 8. Dispatch event to send webhook to ChatGPT (order.created)
        $acpId = $session->getAcpId();
        $orderToken = $order->getTokenValue();
        if ($acpId === null || $orderToken === null) {
            throw new \LogicException('Session must have ACP ID and order must have token value');
        }

        $this->eventBus->dispatch(
            new ACPOrderCompleted($acpId, $orderToken),
            [new DispatchAfterCurrentBusStamp()],
        );

        // Normalize to ACP format
        $responseData = $this->normalizer->normalize($session);

        return new JsonResponse($responseData, Response::HTTP_OK);
    }
}
