<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\Resolver;

final class ValueResolver
{
    /**
     * @param class-string $factory
     */
    public function resolve(object $entity, string $factory, string $reference): mixed
    {
        if (str_starts_with($reference, '@')) {
            return $this->callFactoryMethod($factory, substr($reference, 1), $entity);
        }

        return $this->readEntityProperty($entity, $reference);
    }

    /**
     * Converts a value to a string segment (for identifiers) or keeps it raw
     * for property storage.
     */
    public function normalize(mixed $value, string $context): string
    {
        return match (true) {
            $value === null => '',
            \is_string($value) => $value,
            \is_bool($value) => $value ? '1' : '0',
            $value instanceof \BackedEnum => (string) $value->value,
            $value instanceof \DateTimeInterface => $value->format(\DateTimeInterface::ATOM),
            \is_int($value), \is_float($value), $value instanceof \Stringable => (string) $value,
            \is_array($value) => implode('-', array_map(
                fn (mixed $v): string => $this->normalize($v, $context),
                $value
            )),
            default => throw new \RuntimeException(\sprintf(
                'Value for "%s" must be scalar, array of scalars, or null. Got "%s".',
                $context,
                get_debug_type($value)
            )),
        };
    }

    private function callFactoryMethod(string $factory, string $method, object $entity): mixed
    {
        if (!method_exists($factory, $method)) {
            throw new \LogicException(\sprintf(
                'Method "%s::%s()" referenced by #[AsFixtureRegistered] does not exist.',
                $factory,
                $method
            ));
        }

        $reflection = new \ReflectionMethod($factory, $method);
        if (!$reflection->isStatic()) {
            throw new \LogicException(\sprintf(
                'Method "%s::%s()" must be static.',
                $factory,
                $method
            ));
        }

        return $factory::{$method}($entity);
    }

    private function readEntityProperty(object $entity, string $field): mixed
    {
        foreach (['get' . ucfirst($field), 'is' . ucfirst($field), $field] as $accessor) {
            if (method_exists($entity, $accessor)) {
                return $entity->{$accessor}();
            }
        }

        if (property_exists($entity, $field)) {
            $reflection = new \ReflectionProperty($entity, $field);

            return $reflection->getValue($entity);
        }

        throw new \RuntimeException(\sprintf(
            'Cannot read "%s" on entity "%s". '
            . 'Define a getter %s(), a public property, or use "@methodName".',
            $field,
            $entity::class,
            'get' . ucfirst($field)
        ));
    }
}
