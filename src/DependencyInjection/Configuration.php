<?php

declare(strict_types=1);

namespace Errogaht\WaveSpeedAiBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/** Defines security, polling, and cost boundaries for the WaveSpeed transport. */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('wavespeed_ai');
        $root = $tree->getRootNode();
        if (!$root instanceof ArrayNodeDefinition) {
            // Symfony 6.4 exposes a broad NodeDefinition return type while newer
            // releases describe the concrete array node. The runtime contract is
            // identical, and this guard keeps both static metadata variants safe.
            throw new \LogicException('The wavespeed_ai configuration root must be an array node.');
        }
        // Explicit sibling builders keep PHPStan compatible with both Symfony 6.4
        // and 8.x, whose fluent end() annotations return different parent types.
        $children = $root->children();
        $children->scalarNode('api_key')->isRequired()->cannotBeEmpty();
        $children->scalarNode('base_url')->defaultValue('https://api.wavespeed.ai/api/v3')->cannotBeEmpty();
        $children->floatNode('request_timeout')->min(1)->defaultValue(60.0);
        $children->floatNode('upload_timeout')->min(1)->defaultValue(300.0);
        $children->floatNode('polling_timeout')->min(1)->defaultValue(900.0);
        $children->floatNode('poll_interval')->min(2)->defaultValue(2.0);
        $children->floatNode('max_poll_interval')->min(2)->defaultValue(10.0);
        $children->floatNode('default_max_cost_usd')->min(0)->defaultNull();
        $children->scalarNode('webhook_secret')->defaultNull();
        $children->scalarNode('catalog_path')->defaultNull();

        return $tree;
    }
}
