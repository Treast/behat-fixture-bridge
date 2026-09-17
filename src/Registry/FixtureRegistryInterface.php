<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\Registry;

interface FixtureRegistryInterface
{
    public function register(
        object $entity,
        string $name,
        array $properties = [],
        bool $overwrite = false,
    ): void;

    public function has(string $name): bool;

    public function getId(string $name): mixed;

    public function getClass(string $name): string;

    /** @return array<string, mixed> */
    public function getProperties(string $name): array;

    public function hasProperty(string $name, string $property): bool;

    public function getProperty(string $name, string $property): mixed;

    /** @return array<string, array{class: string, ids: array<string, mixed>, properties: array<string, mixed>}> */
    public function all(): array;

    public function flush(): void;

    public function clear(): void;

    public function reload(): void;
}
