<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Enum;

/** Semantic media requirements used by selectors independently of provider-specific field names. */
enum InputModality: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
}
