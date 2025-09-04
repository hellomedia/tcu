<?php

namespace App\Repository\Trait;

use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\ResultSetMapping;

trait RepositoryTrait
{
    private function createQuery(string $dql): Query
    {
        return $this->getEntityManager()->createQuery($dql);
    }

    private function createNativeQuery(string $sql, ResultSetMapping $rsm): NativeQuery
    {
        return $this->getEntityManager()->createNativeQuery($sql, $rsm);
    }
}
