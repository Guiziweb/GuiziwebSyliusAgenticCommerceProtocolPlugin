<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Security;

/**
 * Validates request signatures from ChatGPT per ACP spec
 *
 * Implementation follows existing reference implementations (ShopBridge, Magento):
 * - HMAC SHA256 with shared secret
 * - No JSON canonicalization (raw payload)
 * - Base64url encoded signature
 * - Optional timestamp validation
 *
 * Pattern: HMAC signature verification (symmetric)
 */
final class ACPSignatureValidator
{
    private const int TIMESTAMP_TOLERANCE_SECONDS = 300; // 5 minutes

    /**
     * Verify request signature using shared secret
     *
     * @param string $secret Shared secret for HMAC
     * @param string $signature base64url-encoded signature from request header
     * @param string $payload Request body (raw JSON)
     * @param string|null $timestamp RFC 3339 timestamp
     *
     * @throws SignatureValidationException if signature is invalid
     */
    public function verify(
        string $secret,
        string $signature,
        string $payload,
        ?string $timestamp = null,
    ): void {
        // 1. Validate timestamp to prevent replay attacks
        if ($timestamp !== null) {
            $this->validateTimestamp($timestamp);
        }

        // 2. Decode base64url signature
        $decodedSignature = $this->base64UrlDecode($signature);
        if ($decodedSignature === false) {
            throw new SignatureValidationException('Invalid base64url signature encoding');
        }

        // 3. Compute HMAC signature on raw payload (no canonicalization)
        $expectedSignature = hash_hmac('sha256', $payload, $secret, true);

        // 4. Compare signatures (timing-safe)
        if (!hash_equals($expectedSignature, $decodedSignature)) {
            throw new SignatureValidationException('Signature verification failed');
        }
    }

    /**
     * Validate timestamp to prevent replay attacks
     *
     * @throws SignatureValidationException if timestamp is invalid or too old
     */
    private function validateTimestamp(string $timestamp): void
    {
        try {
            $requestTime = new \DateTimeImmutable($timestamp);
        } catch (\Exception $e) {
            throw new SignatureValidationException('Invalid timestamp format (expected RFC 3339)');
        }

        $now = new \DateTimeImmutable();
        $diff = $now->getTimestamp() - $requestTime->getTimestamp();

        if (abs($diff) > self::TIMESTAMP_TOLERANCE_SECONDS) {
            throw new SignatureValidationException(
                sprintf('Timestamp outside tolerance window (%d seconds)', self::TIMESTAMP_TOLERANCE_SECONDS),
            );
        }
    }

    /**
     * Base64url decode (RFC 4648 Section 5)
     */
    private function base64UrlDecode(string $data): string|false
    {
        // Convert base64url to base64
        $base64 = strtr($data, '-_', '+/');

        // Add padding if needed
        $padLength = strlen($base64) % 4;
        if ($padLength > 0) {
            $base64 .= str_repeat('=', 4 - $padLength);
        }

        return base64_decode($base64, true);
    }
}
