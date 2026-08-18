<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Value;

/** Immutable billable request with raw model inputs retained for schema-driven APIs. */
final class GenerationRequest
{
    /** @param array<string, mixed> $inputs */
    public function __construct(
        public readonly string $modelId,
        public readonly array $inputs,
        public readonly ?string $webhookUrl = null,
    ) {
        if ('' === trim($modelId) || str_contains($modelId, '..')) {
            throw new \InvalidArgumentException('A valid WaveSpeed model ID is required.');
        }
        if (null !== $webhookUrl && !str_starts_with($webhookUrl, 'https://')) {
            throw new \InvalidArgumentException('WaveSpeed webhooks require a public HTTPS URL.');
        }
    }

    /** @param array<string, mixed> $inputs */
    public static function create(string $modelId, array $inputs): self
    {
        return new self($modelId, $inputs);
    }

    public function withWebhook(string $url): self
    {
        return new self($this->modelId, $this->inputs, $url);
    }
}
