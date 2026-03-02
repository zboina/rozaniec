<?php

namespace Rozaniec\RozaniecBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Rozaniec\RozaniecBundle\Entity\Tajemnica;

/**
 * @extends ServiceEntityRepository<Tajemnica>
 */
class TajemnicaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tajemnica::class);
    }

    /**
     * @return Tajemnica[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.czesc', 'c')
            ->leftJoin('t.kolejnosc', 'k')
            ->addSelect('c', 'k')
            ->orderBy('t.pozycja', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
