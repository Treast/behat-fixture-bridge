<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\Registry;

final readonly class RegistryFile
{
    public function __construct(private string $path)
    {
    }

    public function path(): string
    {
        return $this->path;
    }

    /** @return array<string, array{class: string, ids: array<string, mixed>}> */
    public function read(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $raw = file_get_contents($this->path);
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

        return $decoded ?? [];
    }

    public function write(array $data): void
    {
        $dir = \dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new \RuntimeException(\sprintf('Unable to create directory "%s".', $dir));
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        file_put_contents($this->path, $json);
    }

    public function delete(): void
    {
        if (is_file($this->path)) {
            @unlink($this->path);
        }
    }
}
