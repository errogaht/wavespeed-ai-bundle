<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Generation;

use Errogaht\WaveSpeedAiBundle\Contract\WaveSpeedClientInterface;
use Errogaht\WaveSpeedAiBundle\Event\GenerationSubmitted;
use Errogaht\WaveSpeedAiBundle\Exception\CostLimitExceededException;
use Errogaht\WaveSpeedAiBundle\Value\GenerationRequest;
use Errogaht\WaveSpeedAiBundle\Value\Prediction;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/** Estimates every request before submitting it and enforces the configured spending ceiling. */
final class Generator
{
    public function __construct(
        private readonly WaveSpeedClientInterface $client,
        private readonly EventDispatcherInterface $events,
        private readonly ?float $defaultMaxCostUsd,
    ) {
    }

    public function submit(GenerationRequest $request, ?float $maxCostUsd = null): Prediction
    {
        $estimate = $this->client->price($request->modelId, $request->inputs);
        $limit = $maxCostUsd ?? $this->defaultMaxCostUsd;
        if (null !== $limit && $estimate->amount > $limit) {
            throw new CostLimitExceededException(\sprintf('WaveSpeed estimated %s for model "%s", above the USD %.6f limit. No generation was submitted.', $estimate->formatted(), $request->modelId, $limit));
        }
        $prediction = $this->client->submit($request);
        $this->events->dispatch(new GenerationSubmitted($prediction, $estimate));

        return $prediction;
    }
}
