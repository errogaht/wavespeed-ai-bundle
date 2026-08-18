<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\Tests\DependencyInjection;

use Errogaht\WaveSpeedAiBundle\Contract\WaveSpeedClientInterface;
use Errogaht\WaveSpeedAiBundle\DependencyInjection\WaveSpeedAiExtension;
use Errogaht\WaveSpeedAiBundle\Generation\Generator;
use Errogaht\WaveSpeedAiBundle\Model\ModelCatalog;
use Errogaht\WaveSpeedAiBundle\Model\ModelSelector;
use Errogaht\WaveSpeedAiBundle\Webhook\WebhookVerifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\MockHttpClient;

final class WaveSpeedAiExtensionTest extends TestCase
{
    public function testCompilesPublicSymfonyFirstServicesWithoutLeakingCredentials(): void
    {
        // Scenario: a normal Symfony application configures one environment key
        // and receives autowireable client, catalog, selector, guard, and verifier.
        $container = new ContainerBuilder();
        $container->register('http_client', MockHttpClient::class);
        $container->register('logger', NullLogger::class);
        $container->register('event_dispatcher', EventDispatcher::class);
        $container->registerExtension(new WaveSpeedAiExtension());
        $container->loadFromExtension('wavespeed_ai', [
            'api_key' => 'container-test-key',
            'default_max_cost_usd' => 0.25,
            'webhook_secret' => 'whsec_test',
        ]);
        $container->compile();

        self::assertTrue($container->has(WaveSpeedClientInterface::class));
        self::assertTrue($container->has(ModelCatalog::class));
        self::assertTrue($container->has(ModelSelector::class));
        self::assertTrue($container->has(Generator::class));
        self::assertTrue($container->has(WebhookVerifier::class));
        self::assertStringNotContainsString('container-test-key', serialize($container->getServiceIds()));
    }
}
