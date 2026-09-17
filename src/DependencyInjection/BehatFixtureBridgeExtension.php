<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Treast\BehatFixtureBridge\Behat\FixtureBridgeContext;
use Treast\BehatFixtureBridge\Configuration;
use Treast\BehatFixtureBridge\EventListener\FixtureAutoRegistrationListener;
use Treast\BehatFixtureBridge\EventListener\FixtureCommandListener;
use Treast\BehatFixtureBridge\EventListener\RegistryFlushListener;
use Treast\BehatFixtureBridge\IdExtractor\DoctrineIdExtractor;
use Treast\BehatFixtureBridge\IdExtractor\IdExtractorInterface;
use Treast\BehatFixtureBridge\Metadata\FixtureMetadataRegistry;
use Treast\BehatFixtureBridge\Registry\FixtureRegistry;
use Treast\BehatFixtureBridge\Registry\FixtureRegistryInterface;
use Treast\BehatFixtureBridge\Registry\RegistryFile;
use Treast\BehatFixtureBridge\Resolver\ValueResolver;

final class BehatFixtureBridgeExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container
            ->setParameter('behat_fixture_registry.file', $config['file']);

        $container
            ->register(RegistryFile::class, RegistryFile::class)
            ->setArguments([$config['file']])
            ->setPublic(false)
        ;

        $container
            ->register(DoctrineIdExtractor::class, DoctrineIdExtractor::class)
            ->setArguments([new Reference('doctrine')])
            ->setPublic(false)
        ;

        $container
            ->setAlias(IdExtractorInterface::class, DoctrineIdExtractor::class)
            ->setPublic(false)
        ;

        $container
            ->register(FixtureRegistry::class, FixtureRegistry::class)
            ->setArguments([
                new Reference(RegistryFile::class),
                new Reference(IdExtractorInterface::class),
            ])
            ->setPublic(true)
        ;

        $container
            ->setAlias(FixtureRegistryInterface::class, FixtureRegistry::class)
            ->setPublic(true)
        ;

        $container
            ->register(FixtureMetadataRegistry::class, FixtureMetadataRegistry::class)
            ->setArguments([[]])
            ->setPublic(false)
        ;

        $container
            ->register(ValueResolver::class, ValueResolver::class)
            ->setPublic(false)
        ;

        $container
            ->register(FixtureAutoRegistrationListener::class, FixtureAutoRegistrationListener::class)
            ->setArguments([
                new Reference(FixtureMetadataRegistry::class),
                new Reference(FixtureRegistryInterface::class),
                new Reference(ValueResolver::class),
            ])
            ->addTag('doctrine.event_listener', ['event' => 'postPersist'])
            ->setPublic(false)
        ;

        if ($config['auto_flush_on_terminate']) {
            $container
                ->register(RegistryFlushListener::class, RegistryFlushListener::class)
                ->setArguments([new Reference(FixtureRegistryInterface::class)])
                ->addTag('kernel.event_subscriber')
                ->addTag('doctrine.event_listener', ['event' => 'postFlush'])
                ->setPublic(false)
            ;
        }

        if ($config['purge_on_fixtures_load']) {
            $container
                ->register(FixtureCommandListener::class, FixtureCommandListener::class)
                ->setArguments([new Reference(FixtureRegistryInterface::class)])
                ->addTag('kernel.event_subscriber')
                ->setPublic(false)
            ;
        }

        $container
            ->register(FixtureBridgeContext::class, FixtureBridgeContext::class)
            ->setArguments([new Reference(FixtureRegistryInterface::class)])
            ->setPublic(true)
        ;
    }
}
