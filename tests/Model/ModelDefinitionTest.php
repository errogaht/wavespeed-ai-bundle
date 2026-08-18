<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Tests\Model;

use Errogaht\WaveSpeedAiBundle\Enum\InputModality;
use Errogaht\WaveSpeedAiBundle\Model\ModelCatalog;
use PHPUnit\Framework\TestCase;

final class ModelDefinitionTest extends TestCase
{
    public function testReferenceModelExposesSchemaDerivedMultimodalCapabilities(): void
    {
        // Scenario: an AI agent needs a model that combines a prompt, several
        // images, and a reference video without relying on a hard-coded brand list.
        $catalog = new ModelCatalog(\dirname(__DIR__, 2).'/resources/video-models.json');
        $model = $catalog->get('skywork-ai/skyreels-v4/reference-to-video');

        self::assertTrue($model->accepts(InputModality::Text));
        self::assertTrue($model->accepts(InputModality::Image));
        self::assertTrue($model->accepts(InputModality::Video));
        self::assertTrue($model->supportsMultipleImages());
        self::assertTrue($model->supportsMultipleVideos());
        self::assertTrue($model->supportsNativeAudio());
        self::assertContains('480p', $model->resolutions());
        self::assertContains('prompt', $model->requiredInputs());
    }

    public function testBundledSnapshotContainsEveryReviewedVideoFamily(): void
    {
        // Scenario: offline commands remain useful during provider downtime and
        // include a comprehensive snapshot, not a short curated shortlist.
        $catalog = new ModelCatalog(\dirname(__DIR__, 2).'/resources/video-models.json');
        $types = array_unique(array_map(static fn ($model): string => $model->type, $catalog->video()));

        self::assertGreaterThanOrEqual(480, \count($catalog->video()));
        self::assertContains('text-to-video', $types);
        self::assertContains('image-to-video', $types);
        self::assertContains('video-to-video', $types);
        self::assertContains('digital-human', $types);
    }

    public function testOutputVideoDescriptionDoesNotBecomeVideoInputCapability(): void
    {
        // Scenario: a text-to-video schema mentions the generated video in its
        // duration fields, but selectors must not mistake that for a video input.
        $catalog = new ModelCatalog(\dirname(__DIR__, 2).'/resources/video-models.json');
        $model = $catalog->get('pruna-ai/p-video/text-to-video');

        self::assertTrue($model->accepts(InputModality::Text));
        self::assertFalse($model->accepts(InputModality::Video));
        self::assertFalse($model->accepts(InputModality::Image));
    }
}
