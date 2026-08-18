<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Tests\Webhook;

use Errogaht\WaveSpeedAiBundle\Webhook\WebhookVerifier;
use PHPUnit\Framework\TestCase;

final class WebhookVerifierTest extends TestCase
{
    public function testVerifiesPrefixStrippedSignatureAndReplayWindow(): void
    {
        // Scenario: WaveSpeed's whsec_ prefix is metadata, while the remaining
        // bytes are the HMAC key and an old but valid signature must still fail.
        $body = '{"id":"pred_123","status":"completed"}';
        $id = 'pred_123';
        $timestamp = 1_700_000_000;
        $key = 'secret-value';
        $signature = hash_hmac('sha256', $id.'.'.$timestamp.'.'.$body, $key);
        $verifier = new WebhookVerifier('whsec_'.$key);
        $headers = [
            'webhook-id' => $id,
            'webhook-timestamp' => (string) $timestamp,
            'webhook-signature' => 'v3,'.$signature,
        ];

        self::assertTrue($verifier->verify($body, $headers, $timestamp + 10));
        self::assertFalse($verifier->verify($body, $headers, $timestamp + 301));
    }
}
