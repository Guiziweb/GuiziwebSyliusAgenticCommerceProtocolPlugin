<?php

declare(strict_types=1);

namespace Tests\Guiziweb\SyliusAgenticCommerceProtocolPlugin\Unit\Security;

use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Security\ACPSignatureValidator;
use Guiziweb\SyliusAgenticCommerceProtocolPlugin\Security\SignatureValidationException;
use PHPUnit\Framework\TestCase;

final class ACPSignatureValidatorTest extends TestCase
{
    private ACPSignatureValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ACPSignatureValidator();
    }

    /** @test */
    public function it_validates_valid_hmac_signature(): void
    {
        $secret = 'test-secret-key';
        $body = json_encode(['test' => 'data']);

        // Generate HMAC signature
        $signature = hash_hmac('sha256', $body, $secret, true);
        $encodedSignature = $this->base64UrlEncode($signature);

        // Should not throw exception
        $this->validator->verify($secret, $encodedSignature, $body);

        $this->assertTrue(true); // If we get here, validation passed
    }

    /** @test */
    public function it_rejects_invalid_signature(): void
    {
        $secret = 'test-secret-key';
        $body = json_encode(['test' => 'data']);
        $invalidSignature = $this->base64UrlEncode('invalid_signature_bytes');

        $this->expectException(SignatureValidationException::class);
        $this->expectExceptionMessage('Signature verification failed');

        $this->validator->verify($secret, $invalidSignature, $body);
    }

    /** @test */
    public function it_rejects_signature_with_wrong_secret(): void
    {
        $correctSecret = 'correct-secret';
        $wrongSecret = 'wrong-secret';
        $body = json_encode(['test' => 'data']);

        // Sign with correct secret
        $signature = hash_hmac('sha256', $body, $correctSecret, true);
        $encodedSignature = $this->base64UrlEncode($signature);

        // Verify with wrong secret
        $this->expectException(SignatureValidationException::class);
        $this->expectExceptionMessage('Signature verification failed');

        $this->validator->verify($wrongSecret, $encodedSignature, $body);
    }

    /** @test */
    public function it_rejects_tampered_body(): void
    {
        $secret = 'test-secret-key';
        $originalBody = json_encode(['test' => 'data']);

        // Sign original body
        $signature = hash_hmac('sha256', $originalBody, $secret, true);
        $encodedSignature = $this->base64UrlEncode($signature);

        // Tamper with body
        $tamperedBody = json_encode(['test' => 'tampered']);

        $this->expectException(SignatureValidationException::class);
        $this->expectExceptionMessage('Signature verification failed');

        $this->validator->verify($secret, $encodedSignature, $tamperedBody);
    }

    /** @test */
    public function it_validates_timestamp_within_tolerance(): void
    {
        $secret = 'test-secret-key';
        $body = json_encode(['test' => 'data']);

        $signature = hash_hmac('sha256', $body, $secret, true);
        $encodedSignature = $this->base64UrlEncode($signature);

        // Timestamp within tolerance (current time)
        $timestamp = (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339);

        // Should not throw exception
        $this->validator->verify($secret, $encodedSignature, $body, $timestamp);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_rejects_timestamp_outside_tolerance(): void
    {
        $secret = 'test-secret-key';
        $body = json_encode(['test' => 'data']);

        $signature = hash_hmac('sha256', $body, $secret, true);
        $encodedSignature = $this->base64UrlEncode($signature);

        // Timestamp 10 minutes in the past (outside 5-minute tolerance)
        $timestamp = (new \DateTimeImmutable('-10 minutes'))->format(\DateTimeInterface::RFC3339);

        $this->expectException(SignatureValidationException::class);
        $this->expectExceptionMessage('Timestamp outside tolerance window');

        $this->validator->verify($secret, $encodedSignature, $body, $timestamp);
    }

    /** @test */
    public function it_rejects_invalid_timestamp_format(): void
    {
        $secret = 'test-secret-key';
        $body = json_encode(['test' => 'data']);

        $signature = hash_hmac('sha256', $body, $secret, true);
        $encodedSignature = $this->base64UrlEncode($signature);

        $this->expectException(SignatureValidationException::class);
        $this->expectExceptionMessage('Invalid timestamp format');

        $this->validator->verify($secret, $encodedSignature, $body, 'invalid-timestamp');
    }

    /** @test */
    public function it_rejects_invalid_base64url_encoding(): void
    {
        $secret = 'test-secret-key';
        $body = json_encode(['test' => 'data']);

        // Invalid base64url (contains invalid characters)
        $invalidSignature = '!!!invalid!!!';

        $this->expectException(SignatureValidationException::class);

        $this->validator->verify($secret, $invalidSignature, $body);
    }

    /** @test */
    public function it_handles_different_json_formatting(): void
    {
        $secret = 'test-secret-key';

        // Two JSON strings with same data but different formatting
        $body1 = '{"a":"value1","b":"value2"}';
        $body2 = '{"a": "value1", "b": "value2"}';

        // Each needs its own signature (no canonicalization)
        $signature1 = hash_hmac('sha256', $body1, $secret, true);
        $encodedSignature1 = $this->base64UrlEncode($signature1);

        $signature2 = hash_hmac('sha256', $body2, $secret, true);
        $encodedSignature2 = $this->base64UrlEncode($signature2);

        // Each signature validates against its own body
        $this->validator->verify($secret, $encodedSignature1, $body1);
        $this->validator->verify($secret, $encodedSignature2, $body2);

        // But signature1 does NOT validate against body2 (different whitespace)
        $this->expectException(SignatureValidationException::class);
        $this->validator->verify($secret, $encodedSignature1, $body2);
    }

    /** @test */
    public function it_accepts_base64url_with_or_without_padding(): void
    {
        $secret = 'test-secret-key';
        $body = json_encode(['test' => 'data']);

        $signature = hash_hmac('sha256', $body, $secret, true);

        // With padding
        $withPadding = rtrim(strtr(base64_encode($signature), '+/', '-_'), '');
        // Without padding (already stripped by base64UrlEncode)
        $withoutPadding = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        // Both should work
        $this->validator->verify($secret, $withPadding, $body);
        $this->validator->verify($secret, $withoutPadding, $body);

        $this->assertTrue(true);
    }

    /**
     * Base64url encode helper
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}