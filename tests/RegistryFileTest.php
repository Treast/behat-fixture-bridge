<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\Tests\Registry;

use PHPUnit\Framework\TestCase;
use Treast\BehatFixtureBridge\Registry\RegistryFile;

final class RegistryFileTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/bridge-' . bin2hex(random_bytes(6)) . '/registry.json';
    }

    protected function tearDown(): void
    {
        $dir = \dirname($this->path);
        if (is_dir($dir)) {
            array_map(unlink(...), glob($dir . '/*') ?: []);
            rmdir($dir);
        }
    }

    public function testReadsEmptyArrayWhenFileIsMissing(): void
    {
        $file = new RegistryFile($this->path);

        $this->assertSame([], $file->read());
    }

    public function testWritesAndReadsBackTheSameData(): void
    {
        $file = new RegistryFile($this->path);
        $data = [
            'film:42' => [
                'class' => 'App\\Entity\\Film',
                'ids' => ['id' => 42],
                'properties' => ['titre' => 'Les Misérables', 'annee' => 1982],
            ],
        ];

        $file->write($data);

        $this->assertSame($data, new RegistryFile($this->path)->read());
    }

    public function testCreatesMissingDirectoriesOnWrite(): void
    {
        $file = new RegistryFile($this->path);
        $file->write(['x' => ['class' => 'C', 'ids' => ['id' => 1], 'properties' => []]]);

        $this->assertDirectoryExists(\dirname($this->path));
        $this->assertFileExists($this->path);
    }

    public function testDeleteRemovesTheFile(): void
    {
        $file = new RegistryFile($this->path);
        $file->write(['x' => ['class' => 'C', 'ids' => ['id' => 1], 'properties' => []]]);

        $file->delete();

        $this->assertFileDoesNotExist($this->path);
    }
}
