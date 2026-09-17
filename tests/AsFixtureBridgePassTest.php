<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Treast\BehatFixtureBridge\Attribute\AsFixtureBridge;
use Treast\BehatFixtureBridge\DependencyInjection\CompilerPass\AsFixtureBridgePass;
use Treast\BehatFixtureBridge\Metadata\FixtureMetadataRegistry;

#[AsFixtureBridge(name: 'film', identifier: 'id', properties: ['titre', 'slug'])]
final class AnnotatedFilmFactory
{
    public static function class(): string
    {
        return AnnotatedFilm::class;
    }
}

final class AnnotatedFilm
{
}

#[AsFixtureBridge(name: 'film', identifier: '@compute')]
#[AsFixtureBridge(name: 'oeuvre', identifier: 'id')]
final class MultiAnnotatedFactory
{
    public static function class(): string
    {
        return AnnotatedFilm::class;
    }

    public static function compute(object $entity): string
    {
        return 'x';
    }
}

#[AsFixtureBridge(name: 'broken')]
final class FactoryWithoutClassMethod
{
}

final class AsFixtureBridgePassTest extends TestCase
{
    public function testSkipsContainersWithoutRegistryDefinition(): void
    {
        $container = new ContainerBuilder();

        new AsFixtureBridgePass()->process($container);

        $this->assertFalse($container->hasDefinition(FixtureMetadataRegistry::class));
    }

    public function testCollectsSingleAttribute(): void
    {
        $container = $this->buildContainer(AnnotatedFilmFactory::class);
        $raw = $this->extractRawData($container);

        $this->assertCount(1, $raw);
        $this->assertSame('film', $raw[0]['name']);
        $this->assertSame(['id'], $raw[0]['identifier']);
        $this->assertSame(AnnotatedFilm::class, $raw[0]['entity']);
        $this->assertSame(['titre' => 'titre', 'slug' => 'slug'], $raw[0]['properties']);
    }

    public function testCollectsRepeatableAttributes(): void
    {
        $container = $this->buildContainer(MultiAnnotatedFactory::class);
        $raw = $this->extractRawData($container);

        $this->assertCount(2, $raw);
        $this->assertSame(['film', 'oeuvre'], array_column($raw, 'name'));
    }

    public function testThrowsWhenEntityCannotBeResolved(): void
    {
        $container = $this->buildContainer(FactoryWithoutClassMethod::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/could not be resolved/');
        new AsFixtureBridgePass()->process($container);
    }

    private function buildContainer(string $factoryClass): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register($factoryClass, $factoryClass)->setPublic(false);
        $container->register(FixtureMetadataRegistry::class, FixtureMetadataRegistry::class)
            ->setArguments([[]]);

        return $container;
    }

    /** @return array<array<string, mixed>> */
    private function extractRawData(ContainerBuilder $container): array
    {
        new AsFixtureBridgePass()->process($container);

        return $container->getDefinition(FixtureMetadataRegistry::class)->getArgument(0);
    }
}
