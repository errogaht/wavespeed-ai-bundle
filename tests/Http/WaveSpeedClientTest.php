<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Tests\Http;

use Errogaht\WaveSpeedAiBundle\Contract\SleeperInterface;
use Errogaht\WaveSpeedAiBundle\Http\ResponseMapper;
use Errogaht\WaveSpeedAiBundle\Http\WaveSpeedClient;
use Errogaht\WaveSpeedAiBundle\Value\GenerationRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class WaveSpeedClientTest extends TestCase
{
    public function testPriceUsesFinalInputsWithoutSubmittingGeneration(): void
    {
        // Scenario: exact duration/resolution pricing is checked through the
        // dedicated free endpoint before any inference POST can be made.
        $seen = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            // MockHttpClient normalizes the high-level `json` option into the
            // actual wire body before invoking this callback.
            $seen = [$method, $url, json_decode((string) ($options['body'] ?? ''), true, 512, \JSON_THROW_ON_ERROR)];

            return new MockResponse(json_encode(['code' => 200, 'data' => [
                'model_id' => 'pruna-ai/p-video/text-to-video',
                'unit_price' => 0.02,
                'currency' => 'USD',
            ]], \JSON_THROW_ON_ERROR));
        });
        $estimate = $this->client($http)->price('pruna-ai/p-video/text-to-video', ['prompt' => 'lake', 'duration' => 1]);

        self::assertSame(0.02, $estimate->amount);
        self::assertSame('POST', $seen[0]);
        self::assertStringEndsWith('/model/pricing', $seen[1]);
        self::assertSame(1, $seen[2]['inputs']['duration']);
    }

    public function testSubmitMapsPredictionEnvelopeAndWebhookQuery(): void
    {
        // Scenario: a webhook-enabled request returns immediately and retains the
        // provider result URL for later reconciliation without polling history.
        $seenUrl = null;
        $http = new MockHttpClient(static function (string $method, string $url) use (&$seenUrl): MockResponse {
            $seenUrl = $url;

            return new MockResponse(json_encode(['code' => 200, 'data' => [
                'id' => 'pred_123',
                'status' => 'created',
                'urls' => ['get' => 'https://api.wavespeed.ai/api/v3/predictions/pred_123/result'],
            ]], \JSON_THROW_ON_ERROR));
        });
        $prediction = $this->client($http)->submit(new GenerationRequest(
            'pruna-ai/p-video/text-to-video',
            ['prompt' => 'lake'],
            'https://app.example.com/webhooks/wavespeed',
        ));

        self::assertSame('pred_123', $prediction->id);
        self::assertStringContainsString('webhook=https%3A%2F%2Fapp.example.com', (string) $seenUrl);
    }

    public function testWaitPollsUntilCompletedWithInjectedSleeper(): void
    {
        // Scenario: a worker observes processing then completion, respecting the
        // provider's minimum polling delay without slowing the unit test.
        $responses = [
            new MockResponse('{"code":200,"data":{"id":"pred_123","status":"processing","outputs":[]}}'),
            new MockResponse('{"code":200,"data":{"id":"pred_123","status":"completed","outputs":["https://cdn.example/video.mp4"]}}'),
        ];
        $sleeper = new class implements SleeperInterface {
            /** @var list<float> */
            public array $calls = [];

            public function sleep(float $seconds): void
            {
                $this->calls[] = $seconds;
            }
        };
        $client = $this->client(new MockHttpClient($responses), $sleeper);
        $prediction = $client->wait('pred_123', 5.0);

        self::assertSame(['https://cdn.example/video.mp4'], $prediction->outputs);
        self::assertSame([2.0], $sleeper->calls);
    }

    private function client(MockHttpClient $http, ?SleeperInterface $sleeper = null): WaveSpeedClient
    {
        $sleeper ??= new class implements SleeperInterface {
            public function sleep(float $seconds): void
            {
            }
        };

        return new WaveSpeedClient($http, new ResponseMapper(), $sleeper, new NullLogger(), [
            'api_key' => 'test-key',
            'base_url' => 'https://api.wavespeed.ai/api/v3',
            'request_timeout' => 60.0,
            'upload_timeout' => 300.0,
            'polling_timeout' => 900.0,
            'poll_interval' => 2.0,
            'max_poll_interval' => 10.0,
        ]);
    }
}
