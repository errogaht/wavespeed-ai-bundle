<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Tests\Generation;

use Errogaht\WaveSpeedAiBundle\Contract\WaveSpeedClientInterface;
use Errogaht\WaveSpeedAiBundle\Exception\CostLimitExceededException;
use Errogaht\WaveSpeedAiBundle\Generation\Generator;
use Errogaht\WaveSpeedAiBundle\Value\GenerationRequest;
use Errogaht\WaveSpeedAiBundle\Value\Prediction;
use Errogaht\WaveSpeedAiBundle\Value\PricingEstimate;
use Errogaht\WaveSpeedAiBundle\Value\UploadedMedia;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class GeneratorTest extends TestCase
{
    public function testCostCeilingPreventsSubmission(): void
    {
        // Scenario: an agent accidentally selects expensive settings; the exact
        // estimate exceeds policy and the billable submit method is never called.
        $client = new class implements WaveSpeedClientInterface {
            public bool $submitted = false;

            public function models(bool $videoOnly = false): array
            {
                return [];
            }

            public function price(string $modelId, array $inputs): PricingEstimate
            {
                return new PricingEstimate($modelId, 1.25);
            }

            public function submit(GenerationRequest $request): Prediction
            {
                $this->submitted = true;
                throw new \LogicException('Must not submit');
            }

            public function result(string $predictionId): Prediction
            {
                throw new \LogicException();
            }

            public function wait(string $predictionId, ?float $timeoutSeconds = null): Prediction
            {
                throw new \LogicException();
            }

            public function upload(string $path): UploadedMedia
            {
                throw new \LogicException();
            }
        };
        $generator = new Generator($client, new EventDispatcher(), 0.50);

        try {
            $generator->submit(GenerationRequest::create('vendor/model', ['prompt' => 'test']));
            self::fail('The cost guard should reject the request.');
        } catch (CostLimitExceededException) {
            self::assertFalse($client->submitted);
        }
    }
}
