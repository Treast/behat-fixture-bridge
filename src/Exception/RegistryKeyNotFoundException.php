<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\Exception;

final class RegistryKeyNotFoundException extends \RuntimeException
{
    /** @param array<string> $known */
    public static function forName(string $name, array $known): self
    {
        return new self(\sprintf(
            'No fixture registered under "%s". Known keys: %s',
            $name,
            $known === [] ? '(none)' : implode(', ', $known)
        ));
    }
}
