<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Model;

/** Reads a versioned offline snapshot; live refreshes are provided separately by the API client. */
final class ModelCatalog
{
    /** @var array<string, ModelDefinition>|null */
    private ?array $models = null;

    public function __construct(private readonly string $snapshotPath)
    {
    }

    /** @return list<ModelDefinition> */
    public function all(): array
    {
        return array_values($this->load());
    }

    /** @return list<ModelDefinition> */
    public function video(): array
    {
        return array_values(array_filter($this->all(), static fn (ModelDefinition $model): bool => $model->generatesVideo()));
    }

    public function get(string $id): ModelDefinition
    {
        return $this->load()[$id] ?? throw new \InvalidArgumentException(\sprintf('WaveSpeed model "%s" is not in the bundled catalog. Refresh the catalog or use the live API.', $id));
    }

    /** @return array<string, ModelDefinition> */
    private function load(): array
    {
        if (null !== $this->models) {
            return $this->models;
        }
        $json = @file_get_contents($this->snapshotPath);
        if (false === $json) {
            throw new \RuntimeException(\sprintf('Cannot read WaveSpeed model catalog at "%s".', $this->snapshotPath));
        }
        $rows = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($rows)) {
            throw new \RuntimeException('The WaveSpeed model snapshot must contain a JSON array.');
        }
        $models = [];
        foreach ($rows as $row) {
            if (\is_array($row)) {
                $model = ModelDefinition::fromApi($row);
                if ('' !== $model->id) {
                    $models[$model->id] = $model;
                }
            }
        }

        return $this->models = $models;
    }
}
