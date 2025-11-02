@acp_api @api
Feature: ACP Request Authentication
    In order to secure the ACP API
    As a merchant using the Agentic Commerce Protocol
    I want to ensure all requests are properly authenticated

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "Mug" priced at "$15.00"
        And ACP is enabled for this channel

    @acp_auth
    Scenario: Creating a checkout session without Bearer token fails
        When I create a checkout session without Bearer token
        Then I should receive an unauthorized error

    @acp_auth
    Scenario: Creating a checkout session with invalid Bearer token fails
        When I create a checkout session with invalid Bearer token
        Then I should receive an unauthorized error

    @acp_auth
    Scenario: Creating a checkout session without API-Version header fails
        When I create a checkout session without API-Version header
        Then I should receive a bad request error
        And the error code should be "missing_api_version"

    @acp_auth
    Scenario: Creating a checkout session with unsupported API-Version fails
        When I create a checkout session with API-Version "2024-01-01"
        Then I should receive a bad request error
        And the error code should be "unsupported_api_version"