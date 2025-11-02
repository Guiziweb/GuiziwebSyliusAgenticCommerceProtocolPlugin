# Sylius Agentic Commerce Protocol (ACP) Plugin

[![PHPUnit](https://img.shields.io/badge/tests-186%20passed-brightgreen)](https://github.com/Guiziweb/SyliusAgenticCommerceProtocolPlugin)
[![Behat](https://img.shields.io/badge/behat-31%20scenarios-brightgreen)](https://github.com/Guiziweb/SyliusAgenticCommerceProtocolPlugin)
[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-level%208-brightgreen)](https://github.com/Guiziweb/SyliusAgenticCommerceProtocolPlugin)
[![PHP](https://img.shields.io/badge/PHP-%5E8.2-blue)](https://github.com/Guiziweb/SyliusAgenticCommerceProtocolPlugin)
[![Sylius](https://img.shields.io/badge/Sylius-%5E2.0-blue)](https://github.com/Guiziweb/SyliusAgenticCommerceProtocolPlugin)
[![ACP Spec](https://img.shields.io/badge/ACP-2025--09--29-orange)](https://github.com/agentic-commerce-protocol/agentic-commerce-protocol)

A Sylius plugin implementing OpenAI's **Agentic Commerce Protocol (ACP)** specification, enabling AI agents like ChatGPT to browse products, create checkout sessions, and complete purchases through your Sylius store.

---

## Table of Contents

- [What is ACP?](#what-is-acp)
- [Overview](#overview)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Product Feed](#product-feed)
- [API Endpoints](#api-endpoints)
- [Webhooks](#webhooks)
- [Usage Flow](#usage-flow)
- [Communication Flows](#communication-flows)
- [Security Considerations](#security-considerations)
- [Extension Points](#extension-points)
- [Development](#development)
- [Troubleshooting](#troubleshooting)

---

## What is ACP?

The **Agentic Commerce Protocol (ACP)** is OpenAI's standardized protocol for AI-driven commerce interactions. It defines how AI agents (like ChatGPT) communicate with e-commerce platforms to:

- Browse product catalogs
- Create and manage shopping sessions
- Collect buyer and shipping information
- Process payments securely
- Receive order status updates via webhooks

**Specification version:** `2025-09-29`
**Official spec:** [Agentic Commerce Protocol](https://github.com/agentic-commerce-protocol/agentic-commerce-protocol)

## Overview

This plugin transforms your Sylius store into an **ACP-compliant merchant**, allowing AI agents to interact with your catalog and checkout flow through a standardized REST API.

### Key Features

- **Product Feed Push Command** - Synchronize your Sylius catalog with OpenAI's product feed endpoint
- **Checkout Session Management** - Create, update, retrieve, cancel checkout sessions
- **Delegate Payment** - Secure payment processing via PSP tokens (currently supports **Stripe only**)
- **HMAC Signature Validation** - Authenticate incoming requests from ChatGPT
- **Webhook Notifications** - Notify ChatGPT of order status changes (`order_create`, `order_update`)
- **Idempotency Support** - Handle duplicate requests safely with `Idempotency-Key` headers
- **Channel-Aware** - Full support for Sylius multi-channel architecture

## Architecture

This plugin acts as a **bridge** between OpenAI's ACP protocol and Sylius's e-commerce entities:

- **ACP Checkout Session** → Wraps a Sylius Order (state: `cart`)
- **ACP Line Items** → Sylius Order Items
- **ACP Fulfillment Options** → Sylius Shipping Methods
- **ACP Payment** → Sylius Payment via PSP delegate payment
- **ACP Webhooks** → Notify ChatGPT when orders are created/updated

## Requirements

- **PHP** ^8.2
- **Sylius** ^2.0
- **Symfony** ^6.4

## Installation

### 1. Install via Composer

```bash
composer require guiziweb/sylius-agentic-commerce-protocol-plugin
```

### 2. Enable the Bundle

The bundle should be automatically registered via Symfony Flex. If not, add it manually to `config/bundles.php`:

```php
return [
    // ... other bundles
    Guiziweb\SyliusAgenticCommerceProtocolPlugin\GuiziwebSyliusAgenticCommerceProtocolPlugin::class => ['all' => true],
];
```

### 3. Import Routes

Create `config/routes/guiziweb_sylius_acp.yaml`:

```yaml
guiziweb_sylius_acp:
    resource: "@GuiziwebSyliusAgenticCommerceProtocolPlugin/config/routes.yaml"
```

### 4. Run Database Migrations

```bash
php bin/console doctrine:migrations:migrate -n
```

### 5. Install and Build Assets

```bash
php bin/console assets:install
```

## Configuration

### 1. Create an ACP Payment Method

Navigate to **Sylius Admin → Configuration → Payment Methods** and create a new payment method:

- **Code:** `acp` (or any code you prefer)
- **Name:** `ACP Payment` (visible in admin only - not shown to customers)
- **Gateway:** Select **ACP**
- **Enabled:** Yes
- **Channels:** Select your channel(s)

### 2. Configure Gateway Settings

Fill in the following fields in the payment method configuration:

> **Note:** While the Agentic Commerce Protocol supports multiple Payment Service Providers (PSPs), this plugin currently implements **Stripe only**. Future versions may add support for additional payment providers (PayPal, Adyen, etc.).

#### Stripe Configuration (PSP)
- **PSP URL:** URL of your Payment Service Provider (e.g., `https://api.stripe.com`)
- **PSP Merchant Secret Key:** Your PSP secret key for payment processing (e.g., Stripe secret key)

#### ChatGPT Webhook Configuration
- **ChatGPT Webhook URL:** URL provided by OpenAI to receive order events
  Example: `https://openai.example.com/agentic_checkout/webhooks/order_events`
- **Webhook Secret:** Shared secret for HMAC signature of outgoing webhooks

#### Authentication
- **Bearer Token:** Token used by ChatGPT to authenticate API requests to your store
- **Signature Secret (optional):** Shared secret for HMAC signature verification of incoming requests from ChatGPT

### 3. Configuration Examples

#### Development/Testing Setup
```
PSP URL: https://api.stripe.com
PSP Merchant Secret Key: sk_test_xxxxxxxxxxxxx
ChatGPT Webhook URL: https://openai-dev.example.com/webhooks
Webhook Secret: whsec_dev_xxxxxxxxxxxxxxxx
Bearer Token: dev_token_xxxxxxxxxxxxx
Signature Secret: sig_secret_dev_xxxxxxxxxxxx
```

#### Production Setup
```
PSP URL: https://api.stripe.com
PSP Merchant Secret Key: sk_live_xxxxxxxxxxxxx
ChatGPT Webhook URL: https://api.openai.com/agentic_checkout/webhooks/order_events
Webhook Secret: whsec_live_xxxxxxxxxxxxxxxx
Bearer Token: prod_token_xxxxxxxxxxxxx
Signature Secret: sig_secret_prod_xxxxxxxxxxxx
```

## API Endpoints

Once installed, your Sylius store exposes the following ACP endpoints:

### Product Catalog

The ACP specification does not include a product catalog endpoint. Instead, merchants push their product catalog directly to OpenAI using the **Product Feed Push Command**.

### Checkout Session Management

```http
POST /acp/checkout_sessions
```
Create a new checkout session with items, buyer info, and shipping address.

```http
GET /acp/checkout_sessions/{checkoutSessionId}
```
Retrieve the current state of a checkout session.

```http
POST /acp/checkout_sessions/{checkoutSessionId}
```
Update an existing session (add/remove items, change address, select shipping method).

```http
POST /acp/checkout_sessions/{checkoutSessionId}/complete
```
Finalize the order by providing payment data (PSP token).

```http
POST /acp/checkout_sessions/{checkoutSessionId}/cancel
```
Cancel a checkout session (transitions order to `cancelled` state).

### Authentication

All ACP endpoints require authentication via the **Bearer Token** configured in the payment method:

```http
Authorization: Bearer your_bearer_token_here
```

### HMAC Signature Validation

If **Signature Secret** is configured, incoming requests from ChatGPT must include a valid HMAC signature in the `Signature` header:

```http
Signature: sha256=<hmac_signature_of_request_body>
Timestamp: 2025-01-01T12:00:00+00:00
```

The signature is computed as: `HMAC-SHA256(request_body, signature_secret)`

## Webhooks

The plugin automatically sends webhook notifications to ChatGPT when order events occur.

### Event Types

- **`order_create`** - Sent when an order is successfully created (after payment completion)
- **`order_update`** - Sent when an order status changes (e.g., shipped, fulfilled)

### Webhook Payload

```json
{
  "type": "order_create",
  "data": {
    "type": "order",
    "checkout_session_id": "ses_xxxxxxxxxxxxx",
    "permalink_url": "https://yourstore.com/order/xxxx",
    "status": "created",
    "refunds": []
  }
}
```

### Webhook Signature

Outgoing webhooks are signed with **HMAC-SHA256** using the **Webhook Secret**:

```http
Merchant-Signature: sha256=<hmac_signature>
Request-Id: acp_xxxxxxxxxxxxx
Timestamp: 2025-01-01T12:00:00+00:00
Content-Type: application/json
```

ChatGPT will verify the signature before processing the webhook.

## Usage Flow

### Typical AI Shopping Journey

1. **Browse Products**
   ChatGPT retrieves product catalog from OpenAI's product feed (merchant pushes via batch command)

2. **Create Session**
   User selects items → ChatGPT calls `POST /acp/checkout_sessions` with items

3. **Collect Shipping Info**
   User provides address → ChatGPT calls `POST /acp/checkout_sessions/{id}` with `fulfillment_address`

4. **Select Shipping Method**
   Store returns `fulfillment_options` → User selects method → ChatGPT calls `POST /acp/checkout_sessions/{id}` with `fulfillment_option_id`

5. **Complete Payment**
   ChatGPT obtains PSP token via delegate payment → calls `POST /acp/checkout_sessions/{id}/complete`

6. **Webhook Notification**
   Store sends `order_create` webhook to ChatGPT with order details

7. **Order Confirmation**
   ChatGPT displays order confirmation with permalink to user

## Product Feed

### Pushing Your Catalog to OpenAI

The ACP specification requires merchants to push their product catalog directly to OpenAI. This plugin provides a command to synchronize your Sylius products with OpenAI's product feed system.

#### Prerequisites

**Before using this plugin, you must:**

1. **Register as a merchant** at [chatgpt.com/merchants](https://chatgpt.com/merchants)
2. **Submit a sample feed** to OpenAI's indexing team for validation
3. **Receive your feed endpoint URL** and authentication token from OpenAI after approval
4. **Configure allowed formats** with OpenAI (TSV, CSV, XML, or JSON)

OpenAI will provide you with a secure HTTPS endpoint and credentials during the onboarding process.

#### Configuration

After completing OpenAI's merchant registration, configure the **Product Feed** settings for your channel in Sylius Admin:

1. Navigate to **Marketing → Product Feed Configuration**
2. Select your channel
3. Configure the following fields:

- **Feed Endpoint**: Secure HTTPS endpoint provided by OpenAI during registration
- **Feed Bearer Token**: Authentication token provided by OpenAI
- **Default Brand**: Fallback brand name for products without one
- **Default Weight**: Fallback weight for products (e.g., `"1 kg"`)
- **Default Material**: Fallback material for products (e.g., `"Cotton"`)
- **Return Policy URL**: URL to your return policy page
- **Return Window Days**: Number of days for returns (e.g., `30`)
- **Privacy Policy URL**: URL to your privacy policy page
- **Terms of Service URL**: URL to your terms of service page

#### Command Usage

```bash
php bin/console guiziweb:acp:push-product-feed <channel-code>
```

#### Usage Examples

```bash
# Push products for WEB-US channel
php bin/console guiziweb:acp:push-product-feed WEB-US

# Dry run (see what would be sent without actually sending)
php bin/console guiziweb:acp:push-product-feed WEB-US --dry-run
```

The command will:
1. Fetch all enabled products for the specified channel
2. Include variants with available inventory
3. Format data according to OpenAI's product feed schema
4. Send the catalog to the configured feed endpoint
5. Display progress and summary

#### Scheduling Automatic Sync

To keep your catalog synchronized with OpenAI, schedule the command to run periodically.

**Update Frequency:** OpenAI accepts product feed updates **every 15 minutes**, allowing you to keep pricing and availability current in near real-time.

```yaml
# config/packages/sylius_cron.yaml (if using SchedulerBundle)
scheduler_tasks:
    push_product_feed:
        expression: '*/15 * * * *' # Every 15 minutes (maximum frequency)
        command: 'guiziweb:acp:push-product-feed WEB-US'
```

Or use a system cron job:
```bash
# crontab -e
# Every 15 minutes (maximum frequency supported by OpenAI)
*/15 * * * * cd /path/to/sylius && php bin/console guiziweb:acp:push-product-feed WEB-US

# Alternative: Every hour (recommended for most stores)
0 * * * * cd /path/to/sylius && php bin/console guiziweb:acp:push-product-feed WEB-US
```

**Recommendation:** For most stores, hourly updates are sufficient unless you have highly volatile pricing or inventory. High-frequency updates (every 15 minutes) are best for flash sales or time-sensitive promotions.

## Extension Points

### Custom Order Processing

The plugin dispatches Symfony events during the order lifecycle:

- **`ACPOrderCompleted`** - Dispatched after order is finalized (triggers webhook)

Subscribe to these events to add custom business logic:

```php
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Event\ACPOrderCompleted;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomOrderHandler implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [ACPOrderCompleted::class => 'onOrderCompleted'];
    }

    public function onOrderCompleted(ACPOrderCompleted $event): void
    {
        // Your custom logic here
    }
}
```

### Custom Webhook Events

To send additional webhook events (e.g., `order_update` when order ships), inject `ACPWebhookNotifier`:

```php
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Service\ACPWebhookNotifier;

class ShipmentHandler
{
    public function __construct(private ACPWebhookNotifier $webhookNotifier) {}

    public function onOrderShipped(Order $order): void
    {
        $session = $order->getAcpCheckoutSession(); // Your custom mapping
        $this->webhookNotifier->notify($session, $order, 'order_update', 'shipped');
    }
}
```

## Development

### Running Tests

```bash
# PHPUnit
vendor/bin/phpunit

# Behat
vendor/bin/behat

# PHPStan
vendor/bin/phpstan analyse

# Code Style
vendor/bin/ecs check
```

### Docker Environment

```bash
# Initialize test application
make init

# Setup database
make database-init

# Load fixtures
make load-fixtures

# Run tests
make phpunit
make behat
```

### Database Schema

The plugin creates two tables:

- **`guiziweb_acp_checkout_session`** - Stores ACP session metadata
  Links to:
  - `sylius_order` (one-to-one) - The underlying Sylius order
  - `sylius_channel` (many-to-one) - Channel context

- **`guiziweb_product_feed_config`** - Stores Product Feed configuration per channel
  Links to:
  - `sylius_channel` (many-to-one) - Channel context

## Communication Flows

The plugin orchestrates **three distinct communication flows** as defined by the ACP specification:

### Complete Purchase Flow

```mermaid
sequenceDiagram
    participant User
    participant ChatGPT
    participant YourStore as Your Sylius Store
    participant PSP as Payment Provider (Stripe)

    Note over User,PSP: 1️⃣ BROWSING & CHECKOUT
    User->>ChatGPT: "I want to buy a shirt"
    Note right of ChatGPT: ChatGPT retrieves products<br/>from OpenAI's product feed<br/>(merchant pushes via batch)
    ChatGPT-->>User: Show products

    User->>ChatGPT: "Add blue shirt to cart"
    ChatGPT->>YourStore: POST /acp/checkout_sessions<br/>(Bearer Token + Signature)
    YourStore-->>ChatGPT: Session created (ses_xxx)

    User->>ChatGPT: "Ship to 123 Main St"
    ChatGPT->>YourStore: POST /acp/checkout_sessions/ses_xxx<br/>(fulfillment_address)
    YourStore-->>ChatGPT: Updated session + shipping options

    Note over User,PSP: 2 DELEGATE PAYMENT
    User->>ChatGPT: "Pay with card 4242..."
    ChatGPT->>PSP: POST /agentic_commerce/delegate_payment<br/>(card data + allowance)
    PSP-->>ChatGPT: Vault token (vt_abc123)

    Note over User,PSP: 3 COMPLETE ORDER
    ChatGPT->>YourStore: POST /acp/checkout_sessions/ses_xxx/complete<br/>(token: vt_abc123)
    YourStore->>PSP: POST /agentic_commerce/create_and_process_payment_intent<br/>(PSP Secret Key + vt_abc123)
    PSP-->>YourStore: Payment confirmed
    YourStore-->>ChatGPT: Order completed

    Note over User,PSP: 4 WEBHOOK NOTIFICATION
    YourStore->>ChatGPT: POST /webhooks/order_events<br/>(HMAC Signature)
    ChatGPT-->>YourStore: 200 OK
    ChatGPT-->>User: "Order confirmed"
```

**Important Notes:**
- **Your Sylius Store** (this plugin) implements: `/acp/checkout_sessions/*` + Product Feed Push Command
- **PSP (Stripe)** implements: `/agentic_commerce/delegate_payment`, `/agentic_commerce/create_and_process_payment_intent`
- **ChatGPT (OpenAI)** implements: `/agentic_checkout/webhooks/order_events` + Product Feed storage

The delegate payment flow (step 2) is handled entirely between ChatGPT and the PSP. Your store never sees the raw card data.

## Security Considerations

The plugin handles **three distinct communication flows**, each with its own authentication mechanism:

| Flow | Direction | Authentication | Header | Required | Notes |
|------|-----------|---------------|---------|----------|-------|
| **API Requests** | ChatGPT → Your Store | Bearer Token | `Authorization: Bearer <token>` | ✅ Yes | Rotate regularly. Used for all `/acp/checkout_sessions/*` calls |
| **Request Signature** | ChatGPT → Your Store | HMAC-SHA256 | `Signature: sha256=<hmac>`<br/>`Timestamp: <RFC3339>` | ⚠️ Recommended | Prevents unauthorized access even with leaked Bearer Token |
| **Webhooks** | Your Store → ChatGPT | HMAC-SHA256 | `Merchant-Signature: sha256=<hmac>` | ✅ Yes | OpenAI verifies webhook authenticity. Min. 32 characters secret |
| **PSP Payment** | Your Store → PSP | Secret Key | `Authorization: Bearer <psp_key>` | ✅ Yes | Stripe secret key. Stored encrypted in database |

### Security Best Practices

1. **Rotate tokens regularly** - Change Bearer Tokens and Signature Secrets every 90 days
2. **Use different secrets per environment** - Never reuse dev/staging secrets in production
3. **Enable signature validation** - Configure Signature Secret to prevent Bearer Token abuse
4. **Secure webhook endpoints** - Ensure ChatGPT Webhook URL is HTTPS and validate `Merchant-Signature`
5. **Protect PSP credentials** - Ensure proper database encryption and access controls

## Troubleshooting

### "Payment method not configured for this channel"

Ensure the **ACP** payment method is:
- Created in Sylius Admin
- Enabled
- Assigned to the correct channel

### "Invalid signature" errors

Verify that:
- **Signature Secret** matches what ChatGPT is using
- Request body is being hashed correctly (raw body, no modifications)
- HMAC algorithm is SHA256

### Webhooks not received by ChatGPT

Check that:
- **Webhook URL** is correct and accessible from your server
- **Webhook Secret** is configured
- `ACPOrderCompleted` event is being dispatched (check logs)

### Product feed push fails

Ensure:
- **Product Feed Configuration** is set up for the channel
- **Feed Endpoint** and **Feed Bearer Token** are configured correctly
- Products are **Enabled** in Sylius Admin
- Products have at least one **variant** with inventory
- Products have **channel pricing** configured for the current channel
- Network connectivity to OpenAI's feed endpoint is available

## License

This plugin is released under the [MIT License](LICENSE).

## Credits

Developed by [Guiziweb](https://github.com/Guiziweb) for the Sylius e-commerce platform.

## Support

For issues and feature requests, please use the [GitHub issue tracker](https://github.com/Guiziweb/SyliusAgenticCommerceProtocolPlugin/issues).

## Links

- [Agentic Commerce Protocol Specification](https://github.com/agentic-commerce-protocol/agentic-commerce-protocol)
- [Sylius Documentation](https://docs.sylius.com/)
