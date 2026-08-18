<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Contract;

/** Injectable clock delay keeps polling deterministic under tests and custom runtimes. */
interface SleeperInterface
{
    public function sleep(float $seconds): void;
}
