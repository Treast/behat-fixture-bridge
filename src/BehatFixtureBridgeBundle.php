<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Treast\BehatFixtureBridge\DependencyInjection\BehatFixtureBridgeExtension;
use Treast\BehatFixtureBridge\DependencyInjection\CompilerPass\AsFixtureBridgePass;

final class BehatFixtureBridgeBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new BehatFixtureBridgeExtension();
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new AsFixtureBridgePass());
    }
}
