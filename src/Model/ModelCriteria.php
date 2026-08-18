<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Model;

use Errogaht\WaveSpeedAiBundle\Enum\InputModality;

/** Declarative requirements that an AI agent can use without knowing model IDs. */
final class ModelCriteria
{
    /** @param list<InputModality> $inputs */
    public function __construct(
        public readonly array $inputs = [InputModality::Text],
        public readonly ?string $type = null,
        public readonly ?float $maxBasePriceUsd = null,
        public readonly bool $nativeAudio = false,
        public readonly bool $multipleImages = false,
        public readonly bool $multipleVideos = false,
        public readonly ?string $query = null,
    ) {
    }
}
