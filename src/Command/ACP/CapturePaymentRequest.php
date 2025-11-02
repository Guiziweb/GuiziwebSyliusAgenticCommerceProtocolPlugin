<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Command\ACP;

use Sylius\Bundle\PaymentBundle\Command\PaymentRequestHashAwareInterface;
use Sylius\Bundle\PaymentBundle\Command\PaymentRequestHashAwareTrait;

/**
 * Command to capture ACP Stripe delegated token payment
 *
 * This command is dispatched when completing an ACP checkout session.
 * The handler will charge the Stripe token (spt_xxx) synchronously.
 */
final class CapturePaymentRequest implements PaymentRequestHashAwareInterface
{
    use PaymentRequestHashAwareTrait;

    public function __construct(?string $hash)
    {
        $this->hash = $hash;
    }
}
