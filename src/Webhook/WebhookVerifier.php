<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Webhook;

/** Verifies WaveSpeed v3 HMAC signatures and rejects callbacks outside the replay window. */
final class WebhookVerifier
{
    public function __construct(
        private readonly ?string $secret,
        private readonly int $toleranceSeconds = 300,
    ) {
    }

    /** @param array<string, string> $headers */
    public function verify(string $rawBody, array $headers, ?int $now = null): bool
    {
        if (null === $this->secret || '' === $this->secret) {
            throw new \LogicException('Configure wavespeed_ai.webhook_secret before accepting callbacks.');
        }
        $normalized = array_change_key_case($headers, \CASE_LOWER);
        $id = $normalized['webhook-id'] ?? '';
        $timestamp = $normalized['webhook-timestamp'] ?? '';
        $signature = $normalized['webhook-signature'] ?? '';
        if ('' === $id || !ctype_digit($timestamp) || !str_starts_with($signature, 'v3,')) {
            return false;
        }
        if (abs(($now ?? time()) - (int) $timestamp) > $this->toleranceSeconds) {
            return false;
        }
        $key = str_starts_with($this->secret, 'whsec_') ? substr($this->secret, 6) : $this->secret;
        $expected = hash_hmac('sha256', $id.'.'.$timestamp.'.'.$rawBody, $key);

        return hash_equals($expected, substr($signature, 3));
    }
}
