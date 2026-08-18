<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Value;

/** Temporary WaveSpeed-hosted input; URLs normally expire after seven days. */
final class UploadedMedia
{
    public function __construct(
        public readonly string $type,
        public readonly string $url,
        public readonly string $filename,
        public readonly int $size,
    ) {
    }
}
