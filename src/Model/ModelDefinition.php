<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Model;

use Errogaht\WaveSpeedAiBundle\Enum\InputModality;

/**
 * Schema-backed model description used both for human selection and agent planning.
 *
 * Capabilities are inferred from the live JSON Schema rather than a hand-maintained
 * vendor table, which keeps new WaveSpeed models usable before this bundle releases.
 */
final class ModelDefinition
{
    /** @param array<string, mixed> $requestSchema */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $type,
        public readonly string $description,
        public readonly float $basePriceUsd,
        public readonly string $apiPath,
        public readonly array $requestSchema,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromApi(array $row): self
    {
        $apiSchema = (array) ($row['api_schema'] ?? []);
        $schemas = (array) ($apiSchema['api_schemas'] ?? []);
        $run = [];
        foreach ($schemas as $candidate) {
            if (\is_array($candidate) && ('model_run' === ($candidate['type'] ?? null) || [] === $run)) {
                $run = $candidate;
            }
        }

        return new self(
            (string) ($row['model_id'] ?? ''),
            (string) ($row['name'] ?? $row['model_id'] ?? ''),
            (string) ($row['type'] ?? ''),
            (string) ($row['description'] ?? ''),
            (float) ($row['base_price'] ?? 0.0),
            (string) ($run['api_path'] ?? '/api/v3/'.($row['model_id'] ?? '')),
            \is_array($run['request_schema'] ?? null) ? $run['request_schema'] : [],
        );
    }

    /** @return array<string, array<string, mixed>> */
    public function properties(): array
    {
        $properties = $this->requestSchema['properties'] ?? [];

        return \is_array($properties) ? $properties : [];
    }

    /** @return list<string> */
    public function requiredInputs(): array
    {
        $required = $this->requestSchema['required'] ?? [];

        return array_values(array_filter(\is_array($required) ? $required : [], 'is_string'));
    }

    /** @return list<InputModality> */
    public function inputModalities(): array
    {
        $modalities = [];
        foreach ($this->properties() as $name => $schema) {
            $description = strtolower((string) ($schema['description'] ?? ''));
            $accept = strtolower((string) ($schema['x-ui-component-props']['accept'] ?? ''));
            $field = strtolower($name);
            if ('prompt' === $name || 'text' === $name || str_contains($field, 'prompt')) {
                $modalities[InputModality::Text->value] = InputModality::Text;
            }
            if ($this->isMediaInput($field, $description, $accept, ['image', 'frame'])) {
                $modalities[InputModality::Image->value] = InputModality::Image;
            }
            if ($this->isMediaInput($field, $description, $accept, ['video'])) {
                $modalities[InputModality::Video->value] = InputModality::Video;
            }
            if ($this->isMediaInput($field, $description, $accept, ['audio', 'voice', 'speech'])) {
                $modalities[InputModality::Audio->value] = InputModality::Audio;
            }
        }

        return array_values($modalities);
    }

    public function accepts(InputModality $modality): bool
    {
        return \in_array($modality, $this->inputModalities(), true);
    }

    public function generatesVideo(): bool
    {
        return \in_array($this->type, self::videoTypes(), true)
            || ('lora-support' === $this->type && str_contains(strtolower($this->id.' '.$this->description), 'video'));
    }

    public function supportsNativeAudio(): bool
    {
        foreach ($this->properties() as $name => $schema) {
            $text = strtolower($name.' '.(string) ($schema['description'] ?? ''));
            if ('boolean' === ($schema['type'] ?? null) && (str_contains($text, 'audio') || str_contains($text, 'sound') || str_contains($text, 'bgm'))) {
                return true;
            }
        }

        $description = strtolower($this->description);

        return str_contains($description, 'native audio') || str_contains($description, 'synchronized audio');
    }

    public function supportsMultipleImages(): bool
    {
        return $this->hasMediaArray('image');
    }

    public function supportsMultipleVideos(): bool
    {
        return $this->hasMediaArray('video');
    }

    /** @return list<string> */
    public function resolutions(): array
    {
        $values = [];
        foreach (['resolution', 'size'] as $name) {
            $enum = $this->properties()[$name]['enum'] ?? [];
            foreach (\is_array($enum) ? $enum : [] as $value) {
                if (\is_string($value)) {
                    $values[] = $value;
                }
            }
        }

        return array_values(array_unique($values));
    }

    /** @return array{minimum: int|float|null, maximum: int|float|null, values: list<int|float>} */
    public function durations(): array
    {
        $schema = $this->properties()['duration'] ?? [];
        $enum = array_values(array_filter((array) ($schema['enum'] ?? []), static fn (mixed $value): bool => \is_int($value) || \is_float($value)));

        return [
            'minimum' => is_numeric($schema['minimum'] ?? null) ? (float) $schema['minimum'] : null,
            'maximum' => is_numeric($schema['maximum'] ?? null) ? (float) $schema['maximum'] : null,
            'values' => $enum,
        ];
    }

    /** @return list<string> */
    public static function videoTypes(): array
    {
        return [
            'text-to-video',
            'image-to-video',
            'video-to-video',
            'video-extend',
            'motion-control',
            'digital-human',
            'audio-to-video',
            'portrait-transfer',
            'video-effects',
        ];
    }

    private function hasMediaArray(string $media): bool
    {
        foreach ($this->properties() as $name => $schema) {
            $text = strtolower($name.' '.(string) ($schema['description'] ?? '').' '.(string) ($schema['x-ui-component-props']['accept'] ?? ''));
            if (str_contains($text, $media) && 'array' === ($schema['type'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $terms */
    private function isMediaInput(string $field, string $description, string $accept, array $terms): bool
    {
        foreach ($terms as $term) {
            if (str_contains($field, $term) || str_contains($accept, $term)) {
                return true;
            }
            if (str_contains($description, $term)
                && (str_contains($description, 'input')
                    || str_contains($description, 'reference')
                    || str_contains($description, 'upload')
                    || str_contains($description, ' url')
                    || str_contains($description, 'source'))) {
                return true;
            }
        }

        return false;
    }
}
