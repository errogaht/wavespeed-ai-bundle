<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Contract;

use Errogaht\WaveSpeedAiBundle\Model\ModelDefinition;
use Errogaht\WaveSpeedAiBundle\Value\GenerationRequest;
use Errogaht\WaveSpeedAiBundle\Value\Prediction;
use Errogaht\WaveSpeedAiBundle\Value\PricingEstimate;
use Errogaht\WaveSpeedAiBundle\Value\UploadedMedia;

/** Stable application-facing WaveSpeed operations, normalized away from response envelopes. */
interface WaveSpeedClientInterface
{
    /** @return list<ModelDefinition> */
    public function models(bool $videoOnly = false): array;

    /** @param array<string, mixed> $inputs */
    public function price(string $modelId, array $inputs): PricingEstimate;

    public function submit(GenerationRequest $request): Prediction;

    public function result(string $predictionId): Prediction;

    public function wait(string $predictionId, ?float $timeoutSeconds = null): Prediction;

    public function upload(string $path): UploadedMedia;
}
