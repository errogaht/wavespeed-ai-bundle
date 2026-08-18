<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Enum;

/** WaveSpeed statuses are preserved so async workers can make explicit terminal-state decisions. */
enum PredictionStatus: string
{
    case Created = 'created';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Timeout = 'timeout';
    case Deleted = 'deleted';

    public function isTerminal(): bool
    {
        return !\in_array($this, [self::Created, self::Processing], true);
    }

    public function isSuccessful(): bool
    {
        return self::Completed === $this;
    }
}
