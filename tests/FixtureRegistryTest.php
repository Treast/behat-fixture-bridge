<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\Tests\Registry;

use PHPUnit\Framework\TestCase;
use Treast\BehatFixtureBridge\Exception\RegistryKeyAlreadyExistsException;
use Treast\BehatFixtureBridge\Exception\RegistryKeyNotFoundException;
use Treast\BehatFixtureBridge\IdExtractor\IdExtractorInterface;
use Treast\BehatFixtureBridge\Registry\FixtureRegistry;
use Treast\BehatFixtureBridge\Registry\RegistryFile;

final class FixtureRegistryTest extends TestCase
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

    public function testResolvesSingleIdAsScalar(): void
    {
        $registry = $this->makeRegistry(['id' => 42]);
        $registry->register(new \stdClass(), 'film:42');

        $this->assertSame(42, $registry->getId('film:42'));
    }

    public function testResolvesCompositeIdAsArray(): void
    {
        $registry = $this->makeRegistry(['tenant' => 'acme', 'number' => '001']);
        $registry->register(new \stdClass(), 'order:acme-001');

        $this->assertSame(['tenant' => 'acme', 'number' => '001'], $registry->getId('order:acme-001'));
    }

    public function testStoresPropertiesAlongsideId(): void
    {
        $registry = $this->makeRegistry(['id' => 42]);
        $registry->register(new \stdClass(), 'film:42', [
            'titre' => 'Les Misérables',
            'annee' => 1982,
        ]);

        $this->assertSame('Les Misérables', $registry->getProperty('film:42', 'titre'));
        $this->assertSame(1982, $registry->getProperty('film:42', 'annee'));
        $this->assertTrue($registry->hasProperty('film:42', 'titre'));
        $this->assertFalse($registry->hasProperty('film:42', 'inconnu'));
    }

    public function testThrowsOnUnknownFixtureName(): void
    {
        $registry = $this->makeRegistry();

        $this->expectException(RegistryKeyNotFoundException::class);
        $registry->getId('inconnu');
    }

    public function testThrowsOnUnknownProperty(): void
    {
        $registry = $this->makeRegistry(['id' => 1]);
        $registry->register(new \stdClass(), 'film:1', ['titre' => 'X']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Property "annee".*film:1/');
        $registry->getProperty('film:1', 'annee');
    }

    public function testThrowsOnDuplicateWithoutOverwrite(): void
    {
        $registry = $this->makeRegistry(['id' => 1]);
        $registry->register(new \stdClass(), 'film:1');

        $this->expectException(RegistryKeyAlreadyExistsException::class);
        $registry->register(new \stdClass(), 'film:1');
    }

    public function testOverwriteReplacesTheEntry(): void
    {
        $registry = $this->makeRegistry(['id' => 1]);
        $registry->register(new \stdClass(), 'film:1', ['titre' => 'Avant']);
        $registry->register(new \stdClass(), 'film:1', ['titre' => 'Après'], overwrite: true);

        $this->assertSame('Après', $registry->getProperty('film:1', 'titre'));
    }

    public function testPersistsAcrossInstances(): void
    {
        $writer = $this->makeRegistry(['id' => 7]);
        $writer->register(new \stdClass(), 'user:7', ['email' => 'a@b.c']);
        $writer->flush();

        $reader = $this->makeRegistry();
        $this->assertSame(7, $reader->getId('user:7'));
        $this->assertSame('a@b.c', $reader->getProperty('user:7', 'email'));
    }

    public function testFlushIsIdempotentWithoutChanges(): void
    {
        $registry = $this->makeRegistry(['id' => 1]);
        $registry->register(new \stdClass(), 'x');
        $registry->flush();

        $mtimeBefore = filemtime($this->path);
        $registry->flush();
        clearstatcache();

        $this->assertSame($mtimeBefore, filemtime($this->path));
    }

    public function testClearWipesMemoryAndFile(): void
    {
        $registry = $this->makeRegistry(['id' => 1]);
        $registry->register(new \stdClass(), 'x');
        $registry->flush();

        $registry->clear();

        $this->assertFalse($registry->has('x'));
        $this->assertFileDoesNotExist($this->path);
    }

    public function testReloadPicksUpExternalChanges(): void
    {
        $registry = $this->makeRegistry(['id' => 1]);
        $registry->register(new \stdClass(), 'x');
        $registry->flush();

        $file = new RegistryFile($this->path);
        $data = $file->read();
        $data['y'] = ['class' => 'stdClass', 'ids' => ['id' => 2], 'properties' => []];
        $file->write($data);

        $registry->reload();

        $this->assertSame(2, $registry->getId('y'));
    }

    public function testLoadsLegacyFileWithoutPropertiesKey(): void
    {
        file_put_contents($this->path, json_encode([
            'film:1' => ['class' => 'App\\Entity\\Film', 'ids' => ['id' => 1]],
        ], JSON_THROW_ON_ERROR));

        $registry = $this->makeRegistry();

        $this->assertSame(1, $registry->getId('film:1'));
        $this->assertSame([], $registry->getProperties('film:1'));
    }

    /**
     * @param array<string, mixed>|null $extractedId
     */
    private function makeRegistry(?array $extractedId = null): FixtureRegistry
    {
        $extractor = new readonly class ($extractedId) implements IdExtractorInterface {
            public function __construct(private ?array $id)
            {
            }

            public function extract(object $entity): array
            {
                return $this->id ?? ['id' => 1];
            }
        };

        return new FixtureRegistry(new RegistryFile($this->path), $extractor);
    }
}
