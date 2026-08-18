<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Exception;

/** Marker used by host applications to catch only bundle-originated failures. */
interface WaveSpeedExceptionInterface extends \Throwable
{
}
