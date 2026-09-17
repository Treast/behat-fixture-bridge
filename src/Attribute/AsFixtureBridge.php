<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class AsFixtureBridge
{
    /** @var array<string> */
    public array $identifier;

    /** @var array<string, string>*/
    public array $properties;

    /**
     * @param string|array<string>|null $identifier
     * @param array<string, string>    $properties
     */
    public function __construct(
        public string $name,
        string|array|null $identifier = 'id',
        public ?string $if = null,
        array $properties = [],
    ) {
        $this->identifier = match (true) {
            $identifier === null => [],
            \is_array($identifier) => array_values($identifier),
            default => [$identifier],
        };

        $normalized = [];
        foreach ($properties as $key => $value) {
            if (\is_int($key)) {
                $normalized[ltrim($value, '@')] = $value;
            } else {
                $normalized[$key] = $value;
            }
        }
        $this->properties = $normalized;
    }
}
