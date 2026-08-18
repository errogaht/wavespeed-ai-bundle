<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Value;

use Errogaht\WaveSpeedAiBundle\Enum\PredictionStatus;

/** Normalized prediction state; unknown provider payload fields remain available in raw metadata. */
final class Prediction
{
    /**
     * @param list<string>         $outputs
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly PredictionStatus $status,
        public readonly array $outputs = [],
        public readonly ?string $modelId = null,
        public readonly ?string $resultUrl = null,
        public readonly ?string $error = null,
        public readonly array $metadata = [],
    ) {
    }
}
