<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\DependencyInjection\CompilerPass;

use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Treast\BehatFixtureBridge\Attribute\AsFixtureBridge;
use Treast\BehatFixtureBridge\Metadata\FixtureMetadata;
use Treast\BehatFixtureBridge\Metadata\FixtureMetadataRegistry;

final class AsFixtureBridgePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(FixtureMetadataRegistry::class)) {
            return;
        }

        /** @var array<array{factory: string, entity: string, name: string, identifier: array<string>, if: string|null}> $rawData */
        $rawData = [];

        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass() ?? (class_exists($id) ? $id : null);

            if (null === $class || !class_exists($class)) {
                continue;
            }

            if (!$this->isUserClass($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(AsFixtureBridge::class);

            if ([] === $attributes) {
                continue;
            }

            $entityClass = $this->resolveEntityClass($reflection);
            if (null === $entityClass) {
                throw new \LogicException(\sprintf('Factory "%s" is annotated with #[AsFixtureRegistered] but its entity class could not be resolved (expected a static "class()" or "getClass()" method).', $class));
            }

            foreach ($attributes as $attribute) {
                /** @var AsFixtureBridge $instance */
                $instance = $attribute->newInstance();

                $rawData[] = new FixtureMetadata(
                    factory: $class,
                    entity: $entityClass,
                    name: $instance->name,
                    identifier: $instance->identifier,
                    if: $instance->if,
                    properties: $instance->properties,
                )->toArray();
            }
        }

        $container->setDefinition(
            FixtureMetadataRegistry::class,
            new Definition(FixtureMetadataRegistry::class)
                ->setFactory([FixtureMetadataRegistry::class, 'fromRawData'])
                ->setArguments([$rawData])
                ->setPublic(false),
        );
    }

    /** @param ReflectionClass<object> $factory */
    private function resolveEntityClass(ReflectionClass $factory): ?string
    {
        foreach (['class', 'getClass'] as $methodName) {
            if (!$factory->hasMethod($methodName)) {
                continue;
            }

            $method = $factory->getMethod($methodName);
            if (!$method->isStatic()) {
                continue;
            }

            try {
                $value = $method->invoke(null);
            } catch (\Throwable) {
                continue;
            }

            if (\is_string($value) && class_exists($value)) {
                return $value;
            }
        }

        return null;
    }

    private function isUserClass(string $class): bool
    {
        return !str_starts_with($class, 'Symfony\\')
            && !str_starts_with($class, 'Doctrine\\')
            && !str_starts_with($class, 'Zenstruck\\');
    }
}
