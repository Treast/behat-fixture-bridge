<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\Registry;

use Treast\BehatFixtureBridge\Exception\RegistryKeyAlreadyExistsException;
use Treast\BehatFixtureBridge\Exception\RegistryKeyNotFoundException;
use Treast\BehatFixtureBridge\IdExtractor\IdExtractorInterface;

final class FixtureRegistry implements FixtureRegistryInterface
{
    /** @var array<string, array{class: string, ids: array<string, mixed>, properties: array<string, mixed>}> */
    private array $entries = [];

    private bool $loaded = false;
    private bool $dirty = false;

    public function __construct(
        private readonly RegistryFile $file,
        private readonly IdExtractorInterface $idExtractor,
    ) {
    }

    public function register(
        object $entity,
        string $name,
        array $properties = [],
        bool $overwrite = false,
    ): void {
        $this->ensureLoaded();

        if (!$overwrite && isset($this->entries[$name])) {
            throw RegistryKeyAlreadyExistsException::forName($name, $this->entries[$name]['class']);
        }

        $this->entries[$name] = [
            'class' => $entity::class,
            'ids' => $this->idExtractor->extract($entity),
            'properties' => $properties,
        ];
        $this->dirty = true;
    }

    public function has(string $name): bool
    {
        $this->ensureLoaded();

        return isset($this->entries[$name]);
    }

    public function getId(string $name): mixed
    {
        $this->ensureLoaded();

        if (!isset($this->entries[$name])) {
            throw RegistryKeyNotFoundException::forName($name, array_keys($this->entries));
        }

        $ids = $this->entries[$name]['ids'];

        return \count($ids) === 1 ? reset($ids) : $ids;
    }

    public function getClass(string $name): string
    {
        $this->ensureLoaded();

        if (!isset($this->entries[$name])) {
            throw RegistryKeyNotFoundException::forName($name, array_keys($this->entries));
        }

        return $this->entries[$name]['class'];
    }

    public function getProperties(string $name): array
    {
        $this->ensureLoaded();

        if (!isset($this->entries[$name])) {
            throw RegistryKeyNotFoundException::forName($name, array_keys($this->entries));
        }

        return $this->entries[$name]['properties'];
    }

    public function hasProperty(string $name, string $property): bool
    {
        $this->ensureLoaded();

        return isset($this->entries[$name]['properties'][$property]);
    }

    public function getProperty(string $name, string $property): mixed
    {
        $this->ensureLoaded();

        if (!isset($this->entries[$name])) {
            throw RegistryKeyNotFoundException::forName($name, array_keys($this->entries));
        }

        if (!\array_key_exists($property, $this->entries[$name]['properties'])) {
            throw new \RuntimeException(\sprintf(
                'Property "%s" is not registered for "%s". Available: %s',
                $property,
                $name,
                $this->entries[$name]['properties'] === []
                    ? '(none)'
                    : implode(', ', array_keys($this->entries[$name]['properties']))
            ));
        }

        return $this->entries[$name]['properties'][$property];
    }

    public function all(): array
    {
        $this->ensureLoaded();

        return $this->entries;
    }

    public function flush(): void
    {
        if (!$this->dirty) {
            return;
        }

        $this->file->write($this->entries);
        $this->dirty = false;
    }

    public function clear(): void
    {
        $this->entries = [];
        $this->loaded = true;
        $this->dirty = true;
        $this->file->delete();
    }

    public function reload(): void
    {
        $entries = $this->file->read();

        // Rétro-compat : les anciens fichiers n'ont pas de clé "properties".
        foreach ($entries as $name => $entry) {
            $entries[$name]['properties'] ??= [];
        }

        $this->entries = $entries;
        $this->loaded = true;
        $this->dirty = false;
    }

    private function ensureLoaded(): void
    {
        if (!$this->loaded) {
            $this->reload();
        }
    }
}
