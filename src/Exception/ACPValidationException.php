<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * ACP validation exception with RFC 9535 JSONPath param
 *
 * Used to return structured error responses per ACP spec:
 * - type: 'invalid_request'
 * - code: implementation-defined error code
 * - message: human-readable error message
 * - param: RFC 9535 JSONPath to problematic parameter (optional)
 */
final class ACPValidationException extends BadRequestHttpException
{
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly ?string $param = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getParam(): ?string
    {
        return $this->param;
    }
}
