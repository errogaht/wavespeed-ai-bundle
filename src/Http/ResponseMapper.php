<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Http;

use Errogaht\WaveSpeedAiBundle\Enum\PredictionStatus;
use Errogaht\WaveSpeedAiBundle\Exception\TransportException;
use Errogaht\WaveSpeedAiBundle\Value\Prediction;

/** Converts both submission/result envelopes and direct webhook-shaped rows. */
final class ResponseMapper
{
    /** @param array<string, mixed> $payload */
    public function prediction(array $payload): Prediction
    {
        $data = \is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $status = PredictionStatus::tryFrom((string) ($data['status'] ?? ''));
        if (null === $status || !\is_string($data['id'] ?? null)) {
            throw new TransportException('WaveSpeed returned an incomplete prediction payload.');
        }
        $outputs = array_values(array_filter((array) ($data['outputs'] ?? []), 'is_string'));
        $urls = \is_array($data['urls'] ?? null) ? $data['urls'] : [];

        return new Prediction(
            $data['id'],
            $status,
            $outputs,
            \is_string($data['model'] ?? null) ? $data['model'] : null,
            \is_string($urls['get'] ?? null) ? $urls['get'] : null,
            \is_string($data['error'] ?? null) && '' !== $data['error'] ? $data['error'] : null,
            $data,
        );
    }
}
