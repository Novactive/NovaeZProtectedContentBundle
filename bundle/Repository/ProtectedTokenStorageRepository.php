<?php

namespace Novactive\Bundle\eZProtectedContentBundle\Repository;

use eZ\Publish\API\Repository\Repository;
use Novactive\Bundle\eZProtectedContentBundle\Entity\ProtectedTokenStorage;

class ProtectedTokenStorageRepository extends EntityRepository
{
    protected function getAlias(): string
    {
        return 'pts';
    }

    protected function getEntityClass(): string
    {
        return ProtectedTokenStorage::class;
    }

    public function findByContentId(int $contentId): array
    {
        return $this->findBy(['content_id' => $contentId]);
    }
}
