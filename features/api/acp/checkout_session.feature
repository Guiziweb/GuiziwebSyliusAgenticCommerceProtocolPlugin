@acp_api @api
Feature: Managing checkout sessions via ACP
    In order to enable AI-powered checkout through ChatGPT
    As a merchant using the Agentic Commerce Protocol
    I want to be able to create, retrieve, update, complete, and cancel checkout sessions

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "Mug" priced at "$15.00"
        And the store has a product "T-Shirt" priced at "$25.00"
        And the store has a product "Hoodie" priced at "$50.00"
        And the store ships everywhere for Free
        And ACP is enabled for this channel

    @acp_create_session
    Scenario: Creating a checkout session with items
        When I create a checkout session with items
        Then the checkout session should be created successfully
        And the checkout session status should be "not_ready_for_payment"
        And the checkout session should have 1 items
        And the response should contain total amount

    @acp_create_session
    Scenario: Creating a checkout session with multiple items
        When I create a checkout session with 3 items
        Then the checkout session should be created successfully
        And the checkout session should have 3 items
        And the checkout session status should be "not_ready_for_payment"

    @acp_create_session
    Scenario: Creating a checkout session with invalid data
        When I create a checkout session with invalid data
        Then I should receive a bad request error

    @acp_retrieve_session
    Scenario: Retrieving an existing checkout session
        Given I create a checkout session with items
        And the checkout session should be created successfully
        When I retrieve the checkout session
        Then I should receive a successful response
        And the checkout session status should be "not_ready_for_payment"
        And the checkout session should have 1 items

    @acp_retrieve_session
    Scenario: Retrieving a non-existent checkout session
        When I try to retrieve a non-existent checkout session
        Then I should receive a not found error

    @acp_update_session
    Scenario: Updating checkout session with shipping address
        Given I create a checkout session with items
        And the checkout session should be created successfully
        When I update the checkout session with a shipping address
        Then I should receive a successful response
        And the response should contain shipping address
        And the checkout session status should be "ready_for_payment"

    @acp_update_session
    Scenario: Updating checkout session items
        Given I create a checkout session with items
        And the checkout session should be created successfully
        When I update the checkout session items
        Then I should receive a successful response
        And the checkout session should have 1 items

    @acp_complete_session
    Scenario: Completing a checkout session with payment
        Given I create a checkout session with items
        And the checkout session should be created successfully
        And I update the checkout session with a shipping address
        When I complete the checkout session with payment method "pm_test_123"
        Then I should receive a successful response
        And the checkout session status should be "completed"
        And the response should contain order object

    @acp_complete_session
    Scenario: Completing a checkout session without payment method
        Given I create a checkout session with items
        And the checkout session should be created successfully
        When I try to complete the checkout session without payment method
        Then I should receive a bad request error

    @acp_cancel_session
    Scenario: Canceling an incomplete checkout session
        Given I create a checkout session with items
        And the checkout session should be created successfully
        When I cancel the checkout session
        Then I should receive a successful response
        And the checkout session status should be "canceled"

    @acp_cancel_session
    Scenario: Cannot cancel a completed checkout session
        Given I create a checkout session with items
        And the checkout session should be created successfully
        And I update the checkout session with a shipping address
        And I complete the checkout session with payment method "pm_test_123"
        When I try to cancel the completed checkout session
        Then I should receive a method not allowed error

    @acp_headers
    Scenario: Response headers echo Request-Id and Idempotency-Key
        When I create a checkout session with custom headers
        Then the checkout session should be created successfully
        And the response should contain Request-Id header
        And the response should contain Idempotency-Key header

    @acp_idempotency
    Scenario: Replaying request with same Idempotency-Key returns same session
        When I create a checkout session with items and idempotency key "test-key-replay-123"
        Then the checkout session should be created successfully
        And I save the session ID
        When I create a checkout session with items and idempotency key "test-key-replay-123"
        Then the checkout session should be created successfully
        And the session ID should be the same as saved

    @acp_idempotency
    Scenario: Replaying request with same Idempotency-Key but different parameters returns 409 Conflict
        When I create a checkout session with items and idempotency key "test-key-conflict-456"
        Then the checkout session should be created successfully
        When I create a checkout session with different items and idempotency key "test-key-conflict-456"
        Then I should receive a conflict error
        And the error code should be "idempotency_conflict"

    @acp_validation
    Scenario: Creating a checkout session without Content-Type header fails
        When I create a checkout session without Content-Type header
        Then I should receive a bad request error
        And the error code should be "invalid_content_type"

    @acp_validation
    Scenario: Creating a checkout session with wrong Content-Type fails
        When I create a checkout session with wrong Content-Type
        Then I should receive a bad request error
        And the error code should be "invalid_content_type"

    @acp_validation
    Scenario: Completing checkout without payment_data returns error with param field
        Given I create a checkout session with items
        And the checkout session should be created successfully
        When I try to complete the checkout session without payment_data
        Then I should receive a bad request error
        And the error code should be "missing_parameter"
        And the error param should be "$.payment_data"

    @acp_validation
    Scenario: Updating with invalid fulfillment_option_id returns error with param field
        Given I create a checkout session with items
        And the checkout session should be created successfully
        When I update the checkout session with invalid fulfillment_option_id "invalid_shipping_method"
        Then I should receive a bad request error
        And the error code should be "invalid_parameter"
        And the error param should be "$.fulfillment_option_id"