<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\IdExtractor;

use Doctrine\Persistence\ManagerRegistry;

final readonly class DoctrineIdExtractor implements IdExtractorInterface
{
    public function __construct(private ManagerRegistry $managerRegistry)
    {
    }

    public function extract(object $entity): array
    {
        $manager = $this->managerRegistry->getManagerForClass($entity::class);
        if ($manager === null) {
            throw new \RuntimeException(\sprintf(
                'No Doctrine manager found for "%s".',
                $entity::class
            ));
        }

        $metadata = $manager->getClassMetadata($entity::class);
        $ids = $metadata->getIdentifierValues($entity);

        if ($ids === []) {
            throw new \RuntimeException(\sprintf(
                'Entity "%s" has no identifier value (was it persisted?).',
                $entity::class
            ));
        }

        return $ids;
    }
}
