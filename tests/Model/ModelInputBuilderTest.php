<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Tests\Model;

use Errogaht\WaveSpeedAiBundle\Exception\InvalidRequestException;
use Errogaht\WaveSpeedAiBundle\Model\ModelCatalog;
use Errogaht\WaveSpeedAiBundle\Model\ModelInputBuilder;
use PHPUnit\Framework\TestCase;

final class ModelInputBuilderTest extends TestCase
{
    public function testBuildsImageAndVideoReferenceRequestUsingRealSchemaFields(): void
    {
        // Scenario: callers provide semantic reference roles while the selected
        // model expects provider-specific `images` and `ref_videos` keys.
        $model = (new ModelCatalog(\dirname(__DIR__, 2).'/resources/video-models.json'))
            ->get('skywork-ai/skyreels-v4/reference-to-video');
        $request = (new ModelInputBuilder($model))
            ->prompt('Keep the person and follow the motion reference')
            ->referenceImages(['https://example.com/front.png', 'https://example.com/side.png'])
            ->referenceVideos(['https://example.com/motion.mp4'])
            ->option('duration', 3)
            ->request();

        self::assertSame(['https://example.com/front.png', 'https://example.com/side.png'], $request->inputs['images']);
        self::assertSame(['https://example.com/motion.mp4'], $request->inputs['ref_videos']);
        self::assertSame(3, $request->inputs['duration']);
    }

    public function testRejectsUnknownOptionBeforeItCanBecomeBillable(): void
    {
        // Scenario: a hallucinated agent parameter must fail locally instead of
        // reaching a provider endpoint with unexpected or ignored behavior.
        $model = (new ModelCatalog(\dirname(__DIR__, 2).'/resources/video-models.json'))
            ->get('skywork-ai/skyreels-v4/reference-to-video');

        $this->expectException(InvalidRequestException::class);
        (new ModelInputBuilder($model))->option('imaginary_quality', 'ultra');
    }
}
