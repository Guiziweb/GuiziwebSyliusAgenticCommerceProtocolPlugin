<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Enum;

/**
 * Payment Gateway Factory Names
 *
 * Defines the factory names used to identify payment gateway configurations.
 */
enum PaymentGatewayFactory: string
{
    case ACP = 'acp';

    /**
     * ACP API Version supported by this plugin
     *
     * @see https://developers.openai.com/commerce/specs/checkout/
     */
    public const SUPPORTED_API_VERSION = '2025-09-29';
}
