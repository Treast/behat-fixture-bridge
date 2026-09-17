<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\Tests\EventListener;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use PHPUnit\Framework\TestCase;
use Treast\BehatFixtureBridge\EventListener\FixtureAutoRegistrationListener;
use Treast\BehatFixtureBridge\IdExtractor\IdExtractorInterface;
use Treast\BehatFixtureBridge\Metadata\FixtureMetadata;
use Treast\BehatFixtureBridge\Metadata\FixtureMetadataRegistry;
use Treast\BehatFixtureBridge\Registry\FixtureRegistry;
use Treast\BehatFixtureBridge\Registry\RegistryFile;
use Treast\BehatFixtureBridge\Resolver\ValueResolver;

final class FixtureAutoRegistrationListenerTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/bridge-' . bin2hex(random_bytes(6)) . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function testRegistersEntityWithSimpleIdentifier(): void
    {
        $entity = new TestFilm(42, 'Les Misérables', 'les-miserables');
        $listener = $this->makeListener(
            entity: TestFilm::class,
            identifier: ['id'],
            properties: ['titre' => 'titre', 'slug' => 'slug'],
        );

        $listener->postPersist($this->event($entity));

        $this->assertSame(42, $this->registry->getId('film:42'));
        $this->assertSame('Les Misérables', $this->registry->getProperty('film:42', 'titre'));
    }

    public function testBuildsCompositeKeyFromMultipleSources(): void
    {
        $entity = new TestFilm(42, 'Les Misérables', 'les-miserables');
        $listener = $this->makeListener(
            entity: TestFilm::class,
            identifier: ['slug', '@anneeSuffix'],
        );

        $listener->postPersist($this->event($entity));

        $this->assertTrue($this->registry->has('film:les-miserables-1982'));
    }

    public function testEmptyIdentifierProducesBareName(): void
    {
        $entity = new TestFilm(42, 'X', 'x');
        $listener = $this->makeListener(
            entity: TestFilm::class,
            identifier: [],
        );

        $listener->postPersist($this->event($entity));

        $this->assertTrue($this->registry->has('film'));
    }

    public function testSkipsEntityWhenIfReturnsFalse(): void
    {
        $entity = new TestFilm(42, 'X', 'x', published: false);
        $listener = $this->makeListener(
            entity: TestFilm::class,
            identifier: ['id'],
            if: 'isPublished',
        );

        $listener->postPersist($this->event($entity));

        $this->assertFalse($this->registry->has('film:42'));
    }

    public function testIgnoresEntitiesWithNoMatchingMetadata(): void
    {
        $listener = $this->makeListener(
            entity: TestFilm::class,
            identifier: ['id'],
        );

        $listener->postPersist($this->event(new \stdClass()));

        $this->assertSame([], $this->registry->all());
    }

    public function testDoesNotOverwriteExistingEntry(): void
    {
        $entity = new TestFilm(42, 'Titre 1', 'x');
        $listener = $this->makeListener(
            entity: TestFilm::class,
            identifier: ['id'],
            properties: ['titre' => 'titre'],
        );

        $listener->postPersist($this->event($entity));

        $other = new TestFilm(42, 'Titre 2', 'x');
        $listener->postPersist($this->event($other));

        $this->assertSame('Titre 1', $this->registry->getProperty('film:42', 'titre'));
    }

    private FixtureRegistry $registry;

    private function makeListener(
        string $entity,
        array $identifier,
        array $properties = [],
        ?string $if = null,
    ): FixtureAutoRegistrationListener {
        $metadata = new FixtureMetadata(
            factory: TestFilmFactory::class,
            entity: $entity,
            name: 'film',
            identifier: $identifier,
            if: $if,
            properties: $properties,
        );

        $extractor = new class () implements IdExtractorInterface {
            public function extract(object $entity): array
            {
                return ['id' => $entity->id];
            }
        };

        $this->registry = new FixtureRegistry(
            new RegistryFile($this->path),
            $extractor,
        );

        return new FixtureAutoRegistrationListener(
            new FixtureMetadataRegistry([$metadata]),
            $this->registry,
            new ValueResolver(),
        );
    }

    private function event(object $entity): LifecycleEventArgs
    {
        return new LifecycleEventArgs($entity, $this->createMock(\Doctrine\Persistence\ObjectManager::class));
    }
}

final class TestFilm
{
    public function __construct(
        public int $id,
        public string $titre,
        public string $slug,
        public bool $published = true,
    ) {
    }
}

final class TestFilmFactory
{
    public static function anneeSuffix(object $entity): string
    {
        return '1982';
    }

    public static function isPublished(object $entity): bool
    {
        return $entity->published;
    }
}
