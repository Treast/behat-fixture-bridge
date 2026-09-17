<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('behat_fixture_registry');
        $root = $treeBuilder->getRootNode();

        $root
            ->children()
            ->scalarNode('file')
            ->defaultValue('%kernel.project_dir%/var/fixtures/registry.json')
            ->cannotBeEmpty()
            ->end()
            ->booleanNode('auto_flush_on_terminate')
            ->defaultTrue()
            ->end()
            ->booleanNode('purge_on_fixtures_load')
            ->defaultTrue()
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
