# ACP Behat Integration Tests

This directory contains Behat integration tests for the Agentic Commerce Protocol (ACP) API endpoints.

## Test Structure

```
tests/Behat/
├── Context/
│   └── Api/
│       └── ACPContext.php          # Test implementation for ACP endpoints
└── Resources/
    ├── suites.yml                   # Main suite imports
    └── suites/
        └── api/
            └── acp.yml              # ACP API suite configuration

features/api/acp/
└── checkout_session.feature         # Gherkin scenarios for checkout sessions
```

## Running Tests

### All ACP Tests
```bash
vendor/bin/behat --tags="@acp_api"
```

### Specific Test Suites
```bash
# Create session tests
vendor/bin/behat --tags="@acp_create_session"

# Retrieve session tests
vendor/bin/behat --tags="@acp_retrieve_session"

# Update session tests
vendor/bin/behat --tags="@acp_update_session"

# Complete session tests
vendor/bin/behat --tags="@acp_complete_session"

# Cancel session tests
vendor/bin/behat --tags="@acp_cancel_session"
```

### Using Docker
```bash
make behat
```

## Test Coverage

The tests cover all 5 ACP endpoints as defined in the OpenAPI specification:

1. **POST /acp/checkout_sessions** - Create checkout session
   -  Creating with single item
   -  Creating with multiple items
   -  Error handling for invalid data
   -  Idempotency-Key header support

2. **GET /acp/checkout_sessions/{id}** - Retrieve checkout session
   -  Retrieving existing session
   -  404 error for non-existent session

3. **POST /acp/checkout_sessions/{id}** - Update checkout session
   -  Updating shipping address
   -  Updating items
   -  Status resolution after updates

4. **POST /acp/checkout_sessions/{id}/complete** - Complete checkout
   -  Completing with payment method
   -  Error handling for missing payment method

5. **POST /acp/checkout_sessions/{id}/cancel** - Cancel checkout
   -  Canceling incomplete session
   -  Error handling for completed sessions (405)

## Writing New Tests

### Context Methods

The `ACPContext` class provides reusable step definitions:

```php
/**
 * @When I create a checkout session with items
 */
public function iCreateCheckoutSessionWithItems(): void

/**
 * @When I retrieve the checkout session
 */
public function iRetrieveCheckoutSession(): void

/**
 * @Then the checkout session should be created successfully
 */
public function checkoutSessionShouldBeCreatedSuccessfully(): void
```

### Adding New Scenarios

Add new scenarios to `features/api/acp/checkout_session.feature`:

```gherkin
@acp_api @api @your_tag
Scenario: Your test scenario
    Given some precondition
    When I perform an action
    Then I should see the result
```

## Debugging

### Verbose Output
```bash
vendor/bin/behat --tags="@acp_api" -v
```

### Stop on Failure
```bash
vendor/bin/behat --tags="@acp_api" --stop-on-failure
```

### Dry Run (Check Syntax)
```bash
vendor/bin/behat --tags="@acp_api" --dry-run
```

## Dependencies

The ACP tests use these Sylius contexts:
- `sylius.behat.context.hook.doctrine_orm` - Database setup/teardown
- `sylius.behat.context.transform.channel` - Channel transformations
- `sylius.behat.context.transform.product` - Product transformations
- `sylius.behat.context.setup.channel` - Channel fixtures
- `sylius.behat.context.setup.product` - Product fixtures

## API Client Methods Used

- `buildCreateRequest(resource)` - Prepare POST request
- `buildUpdateRequest(resource, id)` - Prepare PUT/PATCH request
- `show(resource, id)` - GET single resource
- `create()` - Execute POST
- `update()` - Execute PUT/PATCH
- `customAction(path, method, data)` - Execute custom endpoint
- `getLastResponse()` - Get last HTTP response

## Response Checker Methods Used

- `isShowSuccessful(response)` - Check 200 status
- `hasValue(response, key, value)` - Check JSON field value
- `getResponseContent(response)` - Get full JSON response
- `getStatusCode()` - Get HTTP status code