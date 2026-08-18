<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Model;

/** Filters capability-compatible models and ranks cheaper base prices first. */
final class ModelSelector
{
    public function __construct(private readonly ModelCatalog $catalog)
    {
    }

    /** @return list<ModelDefinition> */
    public function recommend(ModelCriteria $criteria, int $limit = 10): array
    {
        $models = array_filter($this->catalog->video(), static function (ModelDefinition $model) use ($criteria): bool {
            if (null !== $criteria->type && $criteria->type !== $model->type) {
                return false;
            }
            foreach ($criteria->inputs as $input) {
                if (!$model->accepts($input)) {
                    return false;
                }
            }
            if (null !== $criteria->maxBasePriceUsd && $model->basePriceUsd > $criteria->maxBasePriceUsd) {
                return false;
            }
            if ($criteria->nativeAudio && !$model->supportsNativeAudio()) {
                return false;
            }
            if ($criteria->multipleImages && !$model->supportsMultipleImages()) {
                return false;
            }
            if ($criteria->multipleVideos && !$model->supportsMultipleVideos()) {
                return false;
            }
            if (null !== $criteria->query) {
                $haystack = strtolower($model->id.' '.$model->name.' '.$model->description);
                if (!str_contains($haystack, strtolower($criteria->query))) {
                    return false;
                }
            }

            return true;
        });
        usort($models, static fn (ModelDefinition $left, ModelDefinition $right): int => [$left->basePriceUsd, $left->id] <=> [$right->basePriceUsd, $right->id]);

        return \array_slice($models, 0, max(0, $limit));
    }
}
