<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\EventListener;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Treast\BehatFixtureBridge\Metadata\FixtureMetadata;
use Treast\BehatFixtureBridge\Metadata\FixtureMetadataRegistry;
use Treast\BehatFixtureBridge\Registry\FixtureRegistryInterface;
use Treast\BehatFixtureBridge\Resolver\ValueResolver;

final readonly class FixtureAutoRegistrationListener
{
    public function __construct(
        private FixtureMetadataRegistry $metadata,
        private FixtureRegistryInterface $registry,
        private ValueResolver $resolver,
    ) {
    }

    public function postPersist(LifecycleEventArgs $event): void
    {
        if ($this->metadata->isEmpty()) {
            return;
        }

        $entity = $event->getObject();

        foreach ($this->metadata->forEntity($entity::class) as $metadata) {
            if (!$this->shouldRegister($entity, $metadata)) {
                continue;
            }

            $key = $this->buildKey($entity, $metadata);

            if ($this->registry->has($key)) {
                continue;
            }

            $this->registry->register(
                $entity,
                $key,
                $this->extractProperties($entity, $metadata),
            );
        }
    }

    private function shouldRegister(object $entity, FixtureMetadata $metadata): bool
    {
        if ($metadata->if === null) {
            return true;
        }

        $factory = $metadata->factory;
        if (!method_exists($factory, $metadata->if)) {
            throw new \LogicException(\sprintf(
                'Method "%s::%s()" declared in #[AsFixtureRegistered(if: ...)] does not exist.',
                $factory,
                $metadata->if
            ));
        }

        return (bool) $factory::{$metadata->if}($entity);
    }

    private function buildKey(object $entity, FixtureMetadata $metadata): string
    {
        if ($metadata->identifier === []) {
            return $metadata->name;
        }

        $parts = [];
        foreach ($metadata->identifier as $part) {
            $value = $this->resolver->resolve($entity, $metadata->factory, $part);
            $normalized = $this->resolver->normalize(
                $value,
                \sprintf('%s::identifier(%s)', $metadata->factory, $part)
            );

            if ($normalized !== '') {
                $parts[] = $normalized;
            }
        }

        return $parts === [] ? $metadata->name : $metadata->name . ':' . implode('-', $parts);
    }

    /** @return array<string, mixed> */
    private function extractProperties(object $entity, FixtureMetadata $metadata): array
    {
        $result = [];

        foreach ($metadata->properties as $storedName => $reference) {
            $value = $this->resolver->resolve($entity, $metadata->factory, $reference);

            $result[$storedName] = $this->normalizeForStorage($value);
        }

        return $result;
    }

    private function normalizeForStorage(mixed $value): mixed
    {
        return match (true) {
            $value === null => null,
            \is_scalar($value) => $value,
            $value instanceof \DateTimeInterface => $value->format(\DateTimeInterface::ATOM),
            $value instanceof \Stringable => (string) $value,
            $value instanceof \BackedEnum => $value->value,
            \is_array($value) => array_map(
                $this->normalizeForStorage(...),
                $value
            ),
            default => throw new \RuntimeException(\sprintf(
                'Property value of type "%s" cannot be serialized to JSON. '
                . 'Return a scalar, a DateTimeInterface, a BackedEnum, or a Stringable.',
                get_debug_type($value)
            )),
        };
    }
}
