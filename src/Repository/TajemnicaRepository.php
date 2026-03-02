<?php

namespace Rozaniec\RozaniecBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Rozaniec\RozaniecBundle\Entity\Tajemnica;
use Rozaniec\RozaniecBundle\Model\RozaniecUserInterface;

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

    /**
     * @return Tajemnica[]
     */
    public function findByUser(RozaniecUserInterface $user): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.czesc', 'c')
            ->leftJoin('t.kolejnosc', 'k')
            ->addSelect('c', 'k')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.pozycja', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
