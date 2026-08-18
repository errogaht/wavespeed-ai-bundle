<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\DependencyInjection;

use Errogaht\WaveSpeedAiBundle\Command\DescribeModelCommand;
use Errogaht\WaveSpeedAiBundle\Command\ModelsCommand;
use Errogaht\WaveSpeedAiBundle\Command\PriceCommand;
use Errogaht\WaveSpeedAiBundle\Contract\SleeperInterface;
use Errogaht\WaveSpeedAiBundle\Contract\WaveSpeedClientInterface;
use Errogaht\WaveSpeedAiBundle\Generation\Generator;
use Errogaht\WaveSpeedAiBundle\Http\NativeSleeper;
use Errogaht\WaveSpeedAiBundle\Http\ResponseMapper;
use Errogaht\WaveSpeedAiBundle\Http\WaveSpeedClient;
use Errogaht\WaveSpeedAiBundle\Model\ModelCatalog;
use Errogaht\WaveSpeedAiBundle\Model\ModelSelector;
use Errogaht\WaveSpeedAiBundle\Webhook\WebhookVerifier;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

/** Registers one explicit, credential-safe client and an offline schema catalog. */
final class WaveSpeedAiExtension extends Extension
{
    public function getAlias(): string
    {
        // Keep the public YAML root aligned with the product name rather than
        // Symfony's automatic `wave_speed_ai` word splitting.
        return 'wavespeed_ai';
    }

    /** @param array<array<string, mixed>> $configs */
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array<string, mixed> $config */
        $config = $this->processConfiguration(new Configuration(), $configs);
        $catalogPath = \is_string($config['catalog_path']) && '' !== $config['catalog_path']
            ? $config['catalog_path']
            : \dirname(__DIR__, 2).'/resources/video-models.json';

        $container->setDefinition(ResponseMapper::class, new Definition(ResponseMapper::class));
        $container->setDefinition(NativeSleeper::class, new Definition(NativeSleeper::class));
        $container->setAlias(SleeperInterface::class, NativeSleeper::class);
        $container->setDefinition(WaveSpeedClient::class, (new Definition(WaveSpeedClient::class, [
            new Reference('http_client'),
            new Reference(ResponseMapper::class),
            new Reference(SleeperInterface::class),
            new Reference('logger'),
            $config,
        ]))->setPublic(true));
        $container->setAlias(WaveSpeedClientInterface::class, new Alias(WaveSpeedClient::class, true));

        $container->setDefinition(ModelCatalog::class, (new Definition(ModelCatalog::class, [$catalogPath]))->setPublic(true));
        $container->setDefinition(ModelSelector::class, (new Definition(ModelSelector::class, [new Reference(ModelCatalog::class)]))->setPublic(true));
        $container->setDefinition(Generator::class, (new Definition(Generator::class, [
            new Reference(WaveSpeedClientInterface::class),
            new Reference('event_dispatcher'),
            $config['default_max_cost_usd'],
        ]))->setPublic(true));
        $container->setDefinition(WebhookVerifier::class, (new Definition(WebhookVerifier::class, [$config['webhook_secret']]))->setPublic(true));

        $container->setDefinition(ModelsCommand::class, (new Definition(ModelsCommand::class, [new Reference(ModelCatalog::class), new Reference(ModelSelector::class)]))->addTag('console.command'));
        $container->setDefinition(DescribeModelCommand::class, (new Definition(DescribeModelCommand::class, [new Reference(ModelCatalog::class)]))->addTag('console.command'));
        $container->setDefinition(PriceCommand::class, (new Definition(PriceCommand::class, [new Reference(WaveSpeedClientInterface::class)]))->addTag('console.command'));
    }
}
