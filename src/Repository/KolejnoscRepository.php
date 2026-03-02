<?php

namespace Rozaniec\RozaniecBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Rozaniec\RozaniecBundle\Entity\Kolejnosc;

/**
 * @extends ServiceEntityRepository<Kolejnosc>
 */
class KolejnoscRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Kolejnosc::class);
    }
}
