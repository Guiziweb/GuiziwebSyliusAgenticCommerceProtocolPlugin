@cli
Feature: Pushing product feed to OpenAI endpoint
    In order to enable AI assistants to search and recommend products
    As a merchant using the Agentic Commerce Protocol
    I want to push my product catalog to the OpenAI feed endpoint

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "Mug" priced at "$15.00"
        And the store has a product "T-Shirt" priced at "$25.00"
        And the channel has product feed configuration

    @push_feed_command
    Scenario: Dry run does not send data to endpoint
        When I run the push feed command for channel "WEB-US" with dry run
        Then the command should succeed
        And no HTTP requests should be made
        And the output should contain "DRY RUN"

    @push_feed_command
    Scenario: Command fails when channel has no feed configuration
        And the channel has no product feed configuration
        When I run the push feed command for channel "WEB-US"
        Then the command should fail
        And the output should contain "No Product Feed configuration found"

    @push_feed_command
    Scenario: Command fails when channel does not exist
        When I run the push feed command for channel "INVALID_CHANNEL"
        Then the command should fail
        And the output should contain "Channel not found"

    @push_feed_command
    Scenario: Command requires channel argument
        When I run the push feed command without channel
        Then the command should fail