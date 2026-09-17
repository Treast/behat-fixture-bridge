<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\Behat;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Treast\BehatFixtureBridge\Registry\FixtureRegistryInterface;

final readonly class FixtureBridgeContext implements Context
{
    public function __construct(
        private FixtureRegistryInterface $registry,
    ) {
    }

    /**
     * @Transform /^(.*@\[[a-zA-Z0-9_.:\-]+\](?:\[[a-zA-Z0-9_]+\])?.*)$/s
     */
    public function resolveFixtures(string $input): mixed
    {
        if (preg_match('/^@\[([a-zA-Z0-9_.:\-]+)\](?:\[([a-zA-Z0-9_]+)\])?$/', $input, $m) === 1) {
            return isset($m[2]) && $m[2] !== ''
                ? $this->registry->getProperty($m[1], $m[2])
                : $this->registry->getId($m[1]);
        }

        return preg_replace_callback(
            '/@\[([a-zA-Z0-9_.:\-]+)\](?:\[([a-zA-Z0-9_]+)\])?/',
            function (array $m): string {
                $value = isset($m[2]) && $m[2] !== ''
                    ? $this->registry->getProperty($m[1], $m[2])
                    : $this->registry->getId($m[1]);

                return $this->stringify($value);
            },
            $input
        ) ?? $input;
    }

    public function fixtureRegistry(): FixtureRegistryInterface
    {
        return $this->registry;
    }

    /**
     * @Given /^I expand fixtures in the following table:$/
     */
    public function iExpandFixturesInTable(TableNode $table): TableNode
    {
        $out = [];
        foreach ($table->getRows() as $row) {
            $out[] = array_map(
                fn (string $cell): string => (string) $this->resolveFixtures($cell),
                $row
            );
        }

        return new TableNode($out);
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            $value instanceof \DateTimeInterface => $value->format(\DateTimeInterface::ATOM),
            $value instanceof \BackedEnum => (string) $value->value,
            \is_scalar($value), $value instanceof \Stringable => (string) $value,
            \is_array($value) => implode('-', array_map($this->stringify(...), $value)),
            default => throw new \RuntimeException(\sprintf(
                'Cannot stringify value of type "%s".',
                get_debug_type($value)
            )),
        };
    }
}
