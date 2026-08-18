<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Http;

use Errogaht\WaveSpeedAiBundle\Contract\SleeperInterface;

final class NativeSleeper implements SleeperInterface
{
    public function sleep(float $seconds): void
    {
        usleep((int) round(max(0.0, $seconds) * 1_000_000));
    }
}
