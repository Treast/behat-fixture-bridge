<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\Metadata;

final readonly class FixtureMetadataRegistry
{
    /** @param array<FixtureMetadata> $metadatas */
    public function __construct(private array $metadatas = [])
    {
    }

    /**
     * @param array<array{factory: string, entity: string, name: string, identifier: array<string>, if: string|null}> $rawData
     */
    public static function fromRawData(array $rawData): self
    {
        $metadatas = array_map(
            FixtureMetadata::fromArray(...),
            $rawData
        );

        return new self($metadatas);
    }

    /** @return array<FixtureMetadata> */
    public function forEntity(string $entityClass): array
    {
        $matches = [];
        foreach ($this->metadatas as $metadata) {
            if ($entityClass === $metadata->entity || is_subclass_of($entityClass, $metadata->entity)) {
                $matches[] = $metadata;
            }
        }

        return $matches;
    }

    /** @return array<FixtureMetadata> */
    public function all(): array
    {
        return $this->metadatas;
    }

    public function isEmpty(): bool
    {
        return $this->metadatas === [];
    }
}
