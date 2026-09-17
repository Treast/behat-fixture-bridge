<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\Factory;

use Treast\BehatFixtureBridge\Registry\FixtureRegistryInterface;

trait FixtureRegistryAwareTrait
{
    protected FixtureRegistryInterface $fixtureRegistry;

    public function setFixtureRegistry(FixtureRegistryInterface $fixtureRegistry): void
    {
        $this->fixtureRegistry = $fixtureRegistry;
    }

    protected function registerFixture(object $entity, string $name, bool $overwrite = false): void
    {
        $this->fixtureRegistry->register($entity, $name, [], $overwrite);
    }
}
