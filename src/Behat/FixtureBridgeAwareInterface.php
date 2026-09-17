<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\Behat;

use Treast\BehatFixtureBridge\Registry\FixtureRegistryInterface;

interface FixtureBridgeAwareInterface
{
    public function setFixtureRegistry(FixtureRegistryInterface $registry): void;
}
