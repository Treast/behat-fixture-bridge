<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\IdExtractor;

interface IdExtractorInterface
{
    /** @return array<string, mixed> */
    public function extract(object $entity): array;
}
