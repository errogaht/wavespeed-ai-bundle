<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Tests\Model;

use Errogaht\WaveSpeedAiBundle\Enum\InputModality;
use Errogaht\WaveSpeedAiBundle\Model\ModelCatalog;
use Errogaht\WaveSpeedAiBundle\Model\ModelCriteria;
use Errogaht\WaveSpeedAiBundle\Model\ModelSelector;
use PHPUnit\Framework\TestCase;

final class ModelSelectorTest extends TestCase
{
    public function testRanksCompatibleTextModelsByBasePrice(): void
    {
        // Scenario: an agent prototyping a prompt should see the cheapest models
        // first while retaining the warning that exact input pricing comes later.
        $catalog = new ModelCatalog(\dirname(__DIR__, 2).'/resources/video-models.json');
        $models = (new ModelSelector($catalog))->recommend(new ModelCriteria(
            inputs: [InputModality::Text],
            type: 'text-to-video',
        ), 5);

        self::assertCount(5, $models);
        self::assertSame('pruna-ai/p-video/text-to-video', $models[0]->id);
        self::assertSame(0.02, $models[0]->basePriceUsd);
        self::assertLessThanOrEqual($models[1]->basePriceUsd, $models[0]->basePriceUsd);
    }
}
