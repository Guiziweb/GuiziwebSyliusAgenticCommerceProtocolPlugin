@acp_api @api
Feature: ACP Request Signature Validation
    In order to ensure secure communication with ChatGPT
    As a merchant using the Agentic Commerce Protocol
    I want to validate HMAC signatures on incoming requests

    @acp_signature
    Scenario: Creating a checkout session without signature header (signature is optional)
        Given the store operates on a single channel in "United States"
        And the store has a product "Mug" priced at "$15.00"
        And ACP is enabled for this channel
        When I create a checkout session with items
        Then the checkout session should be created successfully

    @acp_signature
    Scenario: Creating a checkout session with valid signature
        Given the store operates on a single channel in "United States"
        And the store has a product "Mug" priced at "$15.00"
        And ACP is enabled for this channel
        When I create a checkout session with valid signature
        Then the checkout session should be created successfully

    @acp_signature
    Scenario: Creating a checkout session with invalid signature
        Given the store operates on a single channel in "United States"
        And the store has a product "Mug" priced at "$15.00"
        And ACP is enabled for this channel
        When I create a checkout session with invalid signature
        Then I should receive an unauthorized error
        And the error code should be "signature_validation_failed"

    @acp_signature
    Scenario: Creating a checkout session with signature but no secret configured
        Given the store operates on a single channel in "United States"
        And the store has a product "Mug" priced at "$15.00"
        And ACP is enabled without signature secret
        When I create a checkout session with valid signature
        Then I should receive an unauthorized error
        And the error code should be "signature_validation_failed"
        And the error message should contain "Signature verification is not configured"

    @acp_signature
    Scenario: Creating a checkout session with expired timestamp
        Given the store operates on a single channel in "United States"
        And the store has a product "Mug" priced at "$15.00"
        And ACP is enabled for this channel
        When I create a checkout session with expired timestamp
        Then I should receive an unauthorized error
        And the error code should be "signature_validation_failed"
        And the error message should contain "Timestamp outside tolerance window"