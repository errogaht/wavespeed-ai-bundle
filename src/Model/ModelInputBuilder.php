<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Model;

use Errogaht\WaveSpeedAiBundle\Exception\InvalidRequestException;
use Errogaht\WaveSpeedAiBundle\Value\GenerationRequest;

/**
 * Maps semantic prompt/reference roles onto each model's actual JSON Schema.
 *
 * WaveSpeed models use heterogeneous names (`image`, `images`, `ref_videos`,
 * `reference_urls`, and others). Resolution is schema-driven and fails loudly
 * when no unambiguous compatible field exists, preventing silently billed bad input.
 */
final class ModelInputBuilder
{
    /** @var array<string, mixed> */
    private array $inputs = [];

    public function __construct(private readonly ModelDefinition $model)
    {
    }

    public function prompt(string $prompt): self
    {
        $clone = clone $this;
        $clone->inputs[$this->field(['prompt', 'text', 'description'], 'text prompt', false)] = $prompt;

        return $clone;
    }

    /** @param list<string> $urls */
    public function referenceImages(array $urls): self
    {
        $clone = clone $this;
        [$field, $multiple] = $this->mediaField('image', ['images', 'reference_images', 'reference_urls', 'image_urls', 'image', 'start_image', 'first_frame_image']);
        $clone->inputs[$field] = $multiple ? $urls : $this->single($urls, 'image');

        return $clone;
    }

    /** @param list<string> $urls */
    public function referenceVideos(array $urls): self
    {
        $clone = clone $this;
        [$field, $multiple] = $this->mediaField('video', ['ref_videos', 'reference_videos', 'video_urls', 'videos', 'reference_video', 'video', 'input_video']);
        $clone->inputs[$field] = $multiple ? $urls : $this->single($urls, 'video');

        return $clone;
    }

    public function sourceVideo(string $url): self
    {
        $clone = clone $this;
        $field = $this->field(['video', 'input_video', 'video_url', 'source_video'], 'source video', false);
        $clone->inputs[$field] = $url;

        return $clone;
    }

    public function audio(string $url): self
    {
        $clone = clone $this;
        $field = $this->field(['audio', 'audio_url', 'voice', 'speech'], 'audio', false);
        $clone->inputs[$field] = $url;

        return $clone;
    }

    public function option(string $name, mixed $value): self
    {
        if (!\array_key_exists($name, $this->model->properties())) {
            throw new InvalidRequestException(\sprintf('Model "%s" does not define input "%s".', $this->model->id, $name));
        }
        $clone = clone $this;
        $clone->inputs[$name] = $value;

        return $clone;
    }

    public function request(?string $webhookUrl = null): GenerationRequest
    {
        foreach ($this->model->requiredInputs() as $required) {
            if (!\array_key_exists($required, $this->inputs)) {
                throw new InvalidRequestException(\sprintf('Model "%s" requires input "%s".', $this->model->id, $required));
            }
        }

        return new GenerationRequest($this->model->id, $this->inputs, $webhookUrl);
    }

    /** @param list<string> $preferred */
    private function field(array $preferred, string $role, bool $array): string
    {
        $properties = $this->model->properties();
        foreach ($preferred as $name) {
            if (isset($properties[$name]) && ($array === ('array' === ($properties[$name]['type'] ?? null)))) {
                return $name;
            }
        }

        throw new InvalidRequestException(\sprintf('Model "%s" has no recognized %s input. Use option() with a field from its schema.', $this->model->id, $role));
    }

    /** @param list<string> $preferred
     * @return array{string, bool}
     */
    private function mediaField(string $media, array $preferred): array
    {
        $properties = $this->model->properties();
        foreach ($preferred as $name) {
            if (!isset($properties[$name])) {
                continue;
            }
            $schema = $properties[$name];
            $text = strtolower($name.' '.(string) ($schema['description'] ?? '').' '.(string) ($schema['x-ui-component-props']['accept'] ?? ''));
            if (str_contains($text, $media)) {
                return [$name, 'array' === ($schema['type'] ?? null)];
            }
        }

        throw new InvalidRequestException(\sprintf('Model "%s" has no recognized reference %s input.', $this->model->id, $media));
    }

    /** @param list<string> $urls */
    private function single(array $urls, string $media): string
    {
        if (1 !== \count($urls)) {
            throw new InvalidRequestException(\sprintf('This model accepts exactly one %s in the selected field.', $media));
        }

        return $urls[0];
    }
}
