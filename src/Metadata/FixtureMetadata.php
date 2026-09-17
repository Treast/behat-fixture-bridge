<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\Metadata;

final readonly class FixtureMetadata
{
    /**
     * @param class-string          $factory
     * @param class-string          $entity
     * @param array<string>          $identifier
     * @param array<string, string> $properties
     */
    public function __construct(
        public string $factory,
        public string $entity,
        public string $name,
        public array $identifier,
        public ?string $if,
        public array $properties = [],
    ) {
    }

    /** @return array{factory: string, entity: string, name: string, identifier: array<string>, if: string|null, properties: array<string, string>} */
    public function toArray(): array
    {
        return [
            'factory' => $this->factory,
            'entity' => $this->entity,
            'name' => $this->name,
            'identifier' => $this->identifier,
            'if' => $this->if,
            'properties' => $this->properties,
        ];
    }

    /** @param array{factory: string, entity: string, name: string, identifier: array<string>, if: string|null, properties?: array<string, string>} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            factory: $data['factory'],
            entity: $data['entity'],
            name: $data['name'],
            identifier: $data['identifier'],
            if: $data['if'] ?? null,
            properties: $data['properties'] ?? [],
        );
    }
}
