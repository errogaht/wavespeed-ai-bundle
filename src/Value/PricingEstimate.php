<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Value;

/** Exact provider estimate for one request; callers should still treat final billing as authoritative. */
final class PricingEstimate
{
    public function __construct(
        public readonly string $modelId,
        public readonly float $amount,
        public readonly string $currency = 'USD',
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Price cannot be negative.');
        }
    }

    public function formatted(): string
    {
        return \sprintf('%s %.6f', $this->currency, $this->amount);
    }
}
