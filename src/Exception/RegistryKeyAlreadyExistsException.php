<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\Exception;

final class RegistryKeyAlreadyExistsException extends \RuntimeException
{
    public static function forName(string $name, string $class): self
    {
        return new self(\sprintf(
            'A fixture is already registered under "%s" (class "%s"). Pass overwrite=true to replace it.',
            $name,
            $class
        ));
    }
}
