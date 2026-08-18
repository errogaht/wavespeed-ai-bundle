<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Event;

use Errogaht\WaveSpeedAiBundle\Value\Prediction;
use Errogaht\WaveSpeedAiBundle\Value\PricingEstimate;

/** Emitted after WaveSpeed acknowledges a billable prediction. */
final class GenerationSubmitted
{
    public function __construct(
        public readonly Prediction $prediction,
        public readonly PricingEstimate $estimate,
    ) {
    }
}
