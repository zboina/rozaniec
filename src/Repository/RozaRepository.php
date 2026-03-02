<?php

namespace Rozaniec\RozaniecBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Rozaniec\RozaniecBundle\Entity\Roza;
use Rozaniec\RozaniecBundle\Model\RozaniecUserInterface;

/**
 * @extends ServiceEntityRepository<Roza>
 */
class RozaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Roza::class);
    }

    /**
     * Zwraca róże, w których dany user jest uczestnikiem.
     *
     * @return Roza[]
     */
    public function findByUser(RozaniecUserInterface $user): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.uczestnicy', 'u')
            ->where('u.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.nazwa', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
