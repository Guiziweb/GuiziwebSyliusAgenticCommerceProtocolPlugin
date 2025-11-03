<?php

declare(strict_types=1);

namespace Tests\Guiziweb\SyliusAgenticCommerceProtocolPlugin\Behat\Context\Api;

use Behat\Behat\Context\Context;
use Sylius\Behat\Service\SharedStorageInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

/**
 * Behat context for testing ACP (Agentic Commerce Protocol) endpoints.
 *
 * Tests the 5 ACP endpoints:
 * - POST /acp/checkout_sessions (create)
 * - GET /acp/checkout_sessions/{id} (retrieve)
 * - POST /acp/checkout_sessions/{id} (update)
 * - POST /acp/checkout_sessions/{id}/complete (complete)
 * - POST /acp/checkout_sessions/{id}/cancel (cancel)
 */
final class ACPContext implements Context
{
    private const ACP_API_VERSION = '2025-09-29';

    private const BEARER_TOKEN = 'test_bearer_token_123';

    private const SIGNATURE_SECRET = 'test_signature_secret';

    public function __construct(
        private KernelBrowser $client,
        private SharedStorageInterface $sharedStorage,
    ) {
    }

    /**
     * Encode data to JSON, ensuring it never fails
     */
    private function jsonEncode(mixed $data): string
    {
        $json = json_encode($data);
        Assert::string($json, 'Failed to encode JSON');

        return $json;
    }

    /**
     * Decode JSON string to array
     *
     * @return array<string, mixed>
     */
    private function jsonDecode(string $json): array
    {
        $data = json_decode($json, true);
        Assert::isArray($data, 'Failed to decode JSON or result is not an array');

        return $data;
    }

    /**
     * Get response content as string
     */
    private function getResponseContent(): string
    {
        $content = $this->client->getResponse()->getContent();
        Assert::string($content, 'Response content must be a string');

        return $content;
    }

    /**
     * Get default headers for ACP API requests
     */
    private function getDefaultHeaders(bool $withContentType = false, array $extraHeaders = []): array
    {
        $headers = [
            'HTTP_AUTHORIZATION' => 'Bearer ' . self::BEARER_TOKEN,
            'HTTP_API_VERSION' => self::ACP_API_VERSION,
        ];

        if ($withContentType) {
            $headers['CONTENT_TYPE'] = 'application/json';
        }

        return array_merge($headers, $extraHeaders);
    }

    /**
     * @When I create a checkout session with items
     */
    public function iCreateCheckoutSessionWithItems(): void
    {
        $this->client->request(
            'POST',
            '/acp/checkout_sessions',
            [],
            [],
            $this->getDefaultHeaders(withContentType: true),
            $this->jsonEncode([
                'items' => [
                    [
                        'id' => 'mug',
                        'quantity' => 2,
                    ],
                ],
                'buyer' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'customer@example.com',
                ],
            ]),
        );
    }

    /**
     * @Then the checkout session should be created successfully
     */
    public function checkoutSessionShouldBeCreatedSuccessfully(): void
    {
        $response = $this->client->getResponse();

        Assert::same($response->getStatusCode(), Response::HTTP_CREATED);

        $content = $this->jsonDecode($this->getResponseContent());
        Assert::keyExists($content, 'id');
        Assert::keyExists($content, 'status');

        // Store the checkout session ID for later use
        $this->sharedStorage->set('checkout_session_id', $content['id']);
    }

    /**
     * @Then the checkout session status should be :status
     */
    public function checkoutSessionStatusShouldBe(string $status): void
    {
        $content = $this->jsonDecode($this->getResponseContent());

        Assert::same($content['status'], $status);
    }

    /**
     * @Then the checkout session should have :count items
     */
    public function checkoutSessionShouldHaveItems(int $count): void
    {
        $content = $this->jsonDecode($this->getResponseContent());

        Assert::keyExists($content, 'line_items');
        Assert::count($content['line_items'], $count);
    }

    /**
     * @Then the response should contain buyer email :email
     */
    public function responseShouldContainBuyerEmail(string $email): void
    {
        $content = $this->jsonDecode($this->getResponseContent());

        Assert::keyExists($content, 'buyer_data');
        Assert::keyExists($content['buyer_data'], 'email');
        Assert::same($content['buyer_data']['email'], $email);
    }

    /**
     * @Then the response should contain total amount
     */
    public function responseShouldContainTotalAmount(): void
    {
        $content = $this->jsonDecode($this->getResponseContent());

        Assert::keyExists($content, 'totals');
        Assert::isArray($content['totals']);
        Assert::notEmpty($content['totals']);
    }

    /**
     * @Then the response should contain Idempotency-Key header
     */
    public function responseShouldContainIdempotencyKeyHeader(): void
    {
        $response = $this->client->getResponse();
        $idempotencyKey = $response->headers->get('Idempotency-Key');

        Assert::notNull($idempotencyKey, 'Idempotency-Key header should be present in response');
        Assert::same($idempotencyKey, 'test-idempotency-key-67890', 'Idempotency-Key header should match the sent value');
    }

    /**
     * @When I create a checkout session with :count items
     */
    public function iCreateCheckoutSessionWithCountItems(int $count): void
    {
        // Use real product codes from Background: Mug, T-Shirt, Hoodie
        // Sylius generates codes with underscores, not hyphens
        $productCodes = ['mug', 't_shirt', 'hoodie'];
        $items = [];
        for ($i = 0; $i < $count; ++$i) {
            $items[] = [
                'id' => $productCodes[$i] ?? sprintf('product-%d', $i),
                'quantity' => 1,
            ];
        }

        $this->client->request(
            'POST',
            '/acp/checkout_sessions',
            [],
            [],
            $this->getDefaultHeaders(withContentType: true),
            $this->jsonEncode([
                'items' => $items,
                'buyer' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'customer@example.com',
                ],
            ]),
        );
    }

    /**
     * @When I create a checkout session with invalid data
     */
    public function iCreateCheckoutSessionWithInvalidData(): void
    {
        $this->client->request(
            'POST',
            '/acp/checkout_sessions',
            [],
            [],
            $this->getDefaultHeaders(withContentType: true),
            $this->jsonEncode([
                'items' => 'invalid',
            ]),
        );
    }

    /**
     * @When I retrieve the checkout session
     */
    public function iRetrieveCheckoutSession(): void
    {
        $checkoutSessionId = $this->sharedStorage->get('checkout_session_id');
        $this->client->request(
            'GET',
            sprintf('/acp/checkout_sessions/%s', $checkoutSessionId),
            [],
            [],
            $this->getDefaultHeaders(),
        );
    }

    /**
     * @When I try to retrieve a non-existent checkout session
     */
    public function iTryToRetrieveNonExistentCheckoutSession(): void
    {
        $this->client->request(
            'GET',
            '/acp/checkout_sessions/non-existent-id',
            [],
            [],
            $this->getDefaultHeaders(),
        );
    }

    /**
     * @When I update the checkout session with a shipping address
     */
    public function iUpdateCheckoutSessionWithShippingAddress(): void
    {
        $checkoutSessionId = $this->sharedStorage->get('checkout_session_id');

        // First, add the address
        $this->client->request(
            'POST',
            sprintf('/acp/checkout_sessions/%s', $checkoutSessionId),
            [],
            [],
            $this->getDefaultHeaders(withContentType: true),
            $this->jsonEncode([
                'fulfillment_address' => [
                    'name' => 'John Doe',
                    'line_one' => '123 Main St',
                    'city' => 'New York',
                    'state' => 'NY',
                    'postal_code' => '10001',
                    'country' => 'US',
                ],
            ]),
        );

        // Then get the session to retrieve available fulfillment options
        $response = $this->jsonDecode($this->getResponseContent());
        if (isset($response['fulfillment_options'][0]['id'])) {
            $fulfillmentOptionId = $response['fulfillment_options'][0]['id'];

            // Update again with the selected fulfillment option
            $this->client->request(
                'POST',
                sprintf('/acp/checkout_sessions/%s', $checkoutSessionId),
                [],
                [],
                $this->getDefaultHeaders(withContentType: true),
                $this->jsonEncode([
                    'fulfillment_option_id' => $fulfillmentOptionId,
                ]),
            );
        }
    }

    /**
     * @When I update the checkout session items
     */
    public function iUpdateCheckoutSessionItems(): void
    {
        $checkoutSessionId = $this->sharedStorage->get('checkout_session_id');
        $this->client->request(
            'POST',
            sprintf('/acp/checkout_sessions/%s', $checkoutSessionId),
            [],
            [],
            $this->getDefaultHeaders(withContentType: true),
            $this->jsonEncode([
                'items' => [
                    [
                        'id' => 'mug',
                        'quantity' => 5,
                    ],
                ],
            ]),
        );
    }

    /**
     * @When I complete the checkout session with payment method :paymentMethodId
     */
    public function iCompleteCheckoutSessionWithPaymentMethod(string $paymentMethodId): void
    {
        $checkoutSessionId = $this->sharedStorage->get('checkout_session_id');
        $this->client->request(
            'POST',
            sprintf('/acp/checkout_sessions/%s/complete', $checkoutSessionId),
            [],
            [],
            $this->getDefaultHeaders(withContentType: true),
            $this->jsonEncode([
                'payment_data' => [
                    'token' => $paymentMethodId,
                    'provider' => 'stripe',
                ],
            ]),
        );
    }

    /**
     * @When I try to complete the checkout session without payment method
     */
    public function iTryToCompleteCheckoutSessionWithoutPaymentMethod(): void
    {
        $checkoutSessionId = $this->sharedStorage->get('checkout_session_id');
        $this->client->request(
            'POST',
            sprintf('/acp/checkout_sessions/%s/complete', $checkoutSessionId),
            [],
            [],
            $this->getDefaultHeaders(withContentType: true),
            $this->jsonEncode([]),
        );
    }

    /**
     * @When I cancel the checkout session
     */
    public function iCancelCheckoutSession(): void
    {
        $checkoutSessionId = $this->sharedStorage->get('checkout_session_id');
        $this->client->request(
            'POST',
            sprintf('/acp/checkout_sessions/%s/cancel', $checkoutSessionId),
            [],
            [],
            $this->getDefaultHeaders(),
        );
    }

    /**
     * @When I try to cancel the completed checkout session
     */
    public function iTryToCancelCompletedCheckoutSession(): void
    {
        // Session is already completed in the feature steps, just try to cancel
        $checkoutSessionId = $this->sharedStorage->get('checkout_session_id');
        $this->client->request(
            'POST',
            sprintf('/acp/checkout_sessions/%s/cancel', $checkoutSessionId),
            [],
            [],
            $this->getDefaultHeaders(),
        );
    }

    /**
     * @Then I should receive a successful response
     */
    public function iShouldReceiveSuccessfulResponse(): void
    {
        $response = $this->client->getResponse();

        Assert::same($response->getStatusCode(), Response::HTTP_OK);
    }

    /**
     * @Then I should receive a not found error
     */
    public function iShouldReceiveNotFoundError(): void
    {
        Assert::same($this->client->getResponse()->getStatusCode(), Response::HTTP_NOT_FOUND);
    }

    /**
     * @Then I should receive a bad request error
     */
    public function iShouldReceiveBadRequestError(): void
    {
        Assert::same($this->client->getResponse()->getStatusCode(), Response::HTTP_BAD_REQUEST);
    }

    /**
     * @Then I should receive a method not allowed error
     */
    public function iShouldReceiveMethodNotAllowedError(): void
    {
        Assert::same($this->client->getResponse()->getStatusCode(), Response::HTTP_METHOD_NOT_ALLOWED);
    }

    /**
     * @Then the response should contain shipping address
     */
    public function responseShouldContainShippingAddress(): void
    {
        $content = $this->jsonDecode($this->getResponseContent());

        Assert::keyExists($content, 'fulfillment_address');
        Assert::same($content['fulfillment_address']['name'], 'John Doe');
        Assert::same($content['fulfillment_address']['country'], 'US');
    }

    /**
     * @When I create a checkout session with custom headers
     */
    public function iCreateCheckoutSessionWithCustomHeaders(): void
    {
        $this->client->request(
            'POST',
            '/acp/checkout_sessions',
            [],
            [],
            $this->getDefaultHeaders(withContentType: true, extraHeaders: [
                'HTTP_REQUEST_ID' => 'test-request-id-12345',
                'HTTP_IDEMPOTENCY_KEY' => 'test-idempotency-key-67890',
            ]),
            $this->jsonEncode([
                'items' => [
                    [
                        'id' => 'mug',
                        'quantity' => 1,
                    ],
                ],
                'buyer' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'customer@example.com',
                ],
            ]),
        );
    }

    /**
     * @Then the response should contain Request-Id header
     */
    public function responseShouldContainRequestIdHeader(): void
    {
        $response = $this->client->getResponse();
        $requestId = $response->headers->get('Request-Id');

        Assert::notNull($requestId, 'Request-Id header should be present in response');
        Assert::same($requestId, 'test-request-id-12345', 'Request-Id header should match the sent value');
    }

    /**
     * Generate HMAC SHA256 signature for request body
     */
    private function generateSignature(string $body): string
    {
        $signature = hash_hmac('sha256', $body, self::SIGNATURE_SECRET, true);

        return rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    /**
     * @When I create a checkout session with valid signature
     */
    public function iCreateCheckoutSessionWithValidSignature(): void
    {
        $body = $this->jsonEncode([
            'items' => [
                [
                    'id' => 'mug',
                    'quantity' => 1,
                ],
            ],
            'buyer' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'customer@example.com',
            ],
        ]);

        $timestamp = (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339);
        $signature = $this->generateSignature($body);

        $this->client->request(
            'POST',
            '/acp/checkout_sessions',
            [],
            [],
            $this->getDefaultHeaders(withContentType: true, extraHeaders: [
                'HTTP_SIGNATURE' => $signature,
                'HTTP_TIMESTAMP' => $timestamp,
            ]),
            $body,
        );
    }

    /**
     * @When I create a checkout session with invalid signature
     */
    public function iCreateCheckoutSessionWithInvalidSignature(): void
    {
        $body = $this->jsonEncode([
            'items' => [
                [
                    'id' => 'mug',
                    'quantity' => 1,
                ],
            ],
            'buyer' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'customer@example.com',
            ],
        ]);

        $timestamp = (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339);

        // Invalid signature (just base64url encode random bytes)
        $invalidSignature = rtrim(strtr(base64_encode('invalid_signature'), '+/', '-_'), '=');

        $this->client->request(
            'POST',
            '/acp/checkout_sessions',
            [],
            [],
            $this->getDefaultHeaders(withContentType: true, extraHeaders: [
                'HTTP_SIGNATURE' => $invalidSignature,
                'HTTP_TIMESTAMP' => $timestamp,
            ]),
            $body,
        );
    }

    /**
     * @When I create a checkout session with expired timestamp
     */
    public function iCreateCheckoutSessionWithExpiredTimestamp(): void
    {
        $body = $this->jsonEncode([
            'items' => [
                [
                    'id' => 'mug',
                    'quantity' => 1,
                ],
            ],
            'buyer' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'customer@example.com',
            ],
        ]);

        // Timestamp 10 minutes in the past (outside 5-minute tolerance)
        $expiredTimestamp = (new \DateTimeImmutable('-10 minutes'))->format(\DateTimeInterface::RFC3339);
        $signature = $this->generateSignature($body);

        $this->client->request(
            'POST',
            '/acp/checkout_sessions',
            [],
            [],
            $this->getDefaultHeaders(withContentType: true, extraHeaders: [
                'HTTP_SIGNATURE' => $signature,
                'HTTP_TIMESTAMP' => $expiredTimestamp,
            ]),
            $body,
        );
    }

    /**
     * @Then I should receive an unauthorized error
     */
    public function iShouldReceiveUnauthorizedError(): void
    {
        $response = $this->client->getResponse();

        Assert::same($response->getStatusCode(), Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @Then the error code should be :code
     */
    public function theErrorCodeShouldBe(string $code): void
    {
        $content = $this->jsonDecode($this->getResponseContent());

        Assert::keyExists($content, 'code');
        Assert::same($content['code'], $code);
    }

    /**
     * @Then the error message should contain :message
     */
    public function theErrorMessageShouldContain(string $message): void
    {
        $content = $this->jsonDecode($this->getResponseContent());

        Assert::keyExists($content, 'message');
        Assert::contains($content['message'], $message);
    }

    /**
     * @When I create a checkout session with items and idempotency key :key
     */
    public function iCreateCheckoutSessionWithItemsAndIdempotencyKey(string $key): void
    {
        $this->client->request(
            'POST',
            '/acp/checkout_sessions',
            [],
            [],
            $this->getDefaultHeaders(withContentType: true, extraHeaders: [
                'HTTP_IDEMPOTENCY_KEY' => $key,
            ]),
            $this->jsonEncode([
                'items' => [
                    [
                        'id' => 'mug',
                        'quantity' => 1,
                    ],
                ],
                'buyer' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'customer@example.com',
                ],
            ]),
        );
    }

    /**
     * @Then I save the session ID
     */
    public function iSaveTheSessionId(): void
    {
        $content = $this->jsonDecode($this->getResponseContent());

        Assert::keyExists($content, 'id');
        $this->sharedStorage->set('saved_session_id', $content['id']);
    }

    /**
     * @Then the session ID should be the same as saved
     */
    public function theSessionIdShouldBeTheSameAsSaved(): void
    {
        $content = $this->jsonDecode($this->getResponseContent());

        Assert::keyExists($content, 'id');
        $savedSessionId = $this->sharedStorage->get('saved_session_id');
        Assert::same($content['id'], $savedSessionId, 'Session ID should be the same (idempotency)');
    }

    /**
     * @When I create a checkout session with different items and idempotency key :key
     */
    public function iCreateCheckoutSessionWithDifferentItemsAndIdempotencyKey(string $key): void
    {
        $this->client->request(
            'POST',
            '/acp/checkout_sessions',
            [],
            [],
            $this->getDefaultHeaders(withContentType: true, extraHeaders: [
                'HTTP_IDEMPOTENCY_KEY' => $key,
            ]),
            $this->jsonEncode([
                'items' => [
                    [
                        'id' => 't_shirt',
                        'quantity' => 2,
                    ],
                ],
                'buyer' => [
                    'first_name' => 'Jane',
                    'last_name' => 'Smith',
                    'email' => 'jane@example.com',
                ],
            ]),
        );
    }

    /**
     * @Then I should receive a conflict error
     */
    public function iShouldReceiveConflictError(): void
    {
        Assert::same($this->client->getResponse()->getStatusCode(), Response::HTTP_CONFLICT);
    }

    /**
     * @When I create a checkout session without Content-Type header
     */
    public function iCreateCheckoutSessionWithoutContentTypeHeader(): void
    {
        $this->client->request(
            'POST',
            '/acp/checkout_sessions',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . self::BEARER_TOKEN,
                'HTTP_API_VERSION' => self::ACP_API_VERSION,
                // No Content-Type header
            ],
            $this->jsonEncode([
                'items' => [
                    [
                        'id' => 'mug',
                        'quantity' => 1,
                    ],
                ],
            ]),
        );
    }

    /**
     * @When I create a checkout session with wrong Content-Type
     */
    public function iCreateCheckoutSessionWithWrongContentType(): void
    {
        $this->client->request(
            'POST',
            '/acp/checkout_sessions',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . self::BEARER_TOKEN,
                'HTTP_API_VERSION' => self::ACP_API_VERSION,
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ],
            $this->jsonEncode([
                'items' => [
                    [
                        'id' => 'mug',
                        'quantity' => 1,
                    ],
                ],
            ]),
        );
    }

    /**
     * @When I create a checkout session without Bearer token
     */
    public function iCreateCheckoutSessionWithoutBearerToken(): void
    {
        $this->client->request(
            'POST',
            '/acp/checkout_sessions',
            [],
            [],
            [
                // No Authorization header
                'HTTP_API_VERSION' => self::ACP_API_VERSION,
                'CONTENT_TYPE' => 'application/json',
            ],
            $this->jsonEncode([
                'items' => [
                    [
                        'id' => 'mug',
                        'quantity' => 1,
                    ],
                ],
            ]),
        );
    }

    /**
     * @When I create a checkout session with invalid Bearer token
     */
    public function iCreateCheckoutSessionWithInvalidBearerToken(): void
    {
        $this->client->request(
            'POST',
            '/acp/checkout_sessions',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer invalid_token_xyz',
                'HTTP_API_VERSION' => self::ACP_API_VERSION,
                'CONTENT_TYPE' => 'application/json',
            ],
            $this->jsonEncode([
                'items' => [
                    [
                        'id' => 'mug',
                        'quantity' => 1,
                    ],
                ],
            ]),
        );
    }

    /**
     * @When I create a checkout session without API-Version header
     */
    public function iCreateCheckoutSessionWithoutApiVersionHeader(): void
    {
        $this->client->request(
            'POST',
            '/acp/checkout_sessions',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . self::BEARER_TOKEN,
                // No API-Version header
                'CONTENT_TYPE' => 'application/json',
            ],
            $this->jsonEncode([
                'items' => [
                    [
                        'id' => 'mug',
                        'quantity' => 1,
                    ],
                ],
            ]),
        );
    }

    /**
     * @When I create a checkout session with API-Version :version
     */
    public function iCreateCheckoutSessionWithApiVersion(string $version): void
    {
        $this->client->request(
            'POST',
            '/acp/checkout_sessions',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . self::BEARER_TOKEN,
                'HTTP_API_VERSION' => $version,
                'CONTENT_TYPE' => 'application/json',
            ],
            $this->jsonEncode([
                'items' => [
                    [
                        'id' => 'mug',
                        'quantity' => 1,
                    ],
                ],
            ]),
        );
    }

    /**
     * @Then the response should contain order object
     */
    public function responseShouldContainOrderObject(): void
    {
        $content = $this->jsonDecode($this->getResponseContent());

        // Per ACP spec Section 4.4: Complete response MUST include order object
        Assert::keyExists($content, 'order', 'Response must contain order object');
        Assert::isArray($content['order'], 'order must be an object');

        // Verify required fields per spec
        Assert::keyExists($content['order'], 'id', 'order.id is required');
        Assert::keyExists($content['order'], 'checkout_session_id', 'order.checkout_session_id is required');
        Assert::keyExists($content['order'], 'permalink_url', 'order.permalink_url is required');

        // Verify permalink_url is a valid URL
        Assert::startsWith($content['order']['permalink_url'], 'http', 'permalink_url must be a valid URL');
    }

    /**
     * @When I try to complete the checkout session without payment_data
     */
    public function iTryToCompleteCheckoutSessionWithoutPaymentData(): void
    {
        $checkoutSessionId = $this->sharedStorage->get('checkout_session_id');
        $this->client->request(
            'POST',
            sprintf('/acp/checkout_sessions/%s/complete', $checkoutSessionId),
            [],
            [],
            $this->getDefaultHeaders(withContentType: true),
            $this->jsonEncode([]), // Empty body, missing payment_data
        );
    }

    /**
     * @Then the error param should be :param
     */
    public function theErrorParamShouldBe(string $param): void
    {
        $content = $this->jsonDecode($this->getResponseContent());

        Assert::keyExists($content, 'param', 'Error response must contain param field (RFC 9535 JSONPath)');
        Assert::same($content['param'], $param, sprintf('param should be "%s"', $param));
    }

    /**
     * @When I update the checkout session with invalid fulfillment_option_id :fulfillmentOptionId
     */
    public function iUpdateCheckoutSessionWithInvalidFulfillmentOptionId(string $fulfillmentOptionId): void
    {
        $checkoutSessionId = $this->sharedStorage->get('checkout_session_id');
        $this->client->request(
            'POST',
            sprintf('/acp/checkout_sessions/%s', $checkoutSessionId),
            [],
            [],
            $this->getDefaultHeaders(withContentType: true),
            $this->jsonEncode([
                'fulfillment_option_id' => $fulfillmentOptionId,
            ]),
        );
    }
}
