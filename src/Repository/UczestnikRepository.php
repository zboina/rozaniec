<?php

namespace Rozaniec\RozaniecBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Rozaniec\RozaniecBundle\Entity\Roza;
use Rozaniec\RozaniecBundle\Entity\Uczestnik;
use Rozaniec\RozaniecBundle\Model\RozaniecUserInterface;

/**
 * @extends ServiceEntityRepository<Uczestnik>
 */
class UczestnikRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Uczestnik::class);
    }

    /**
     * @return Uczestnik[]
     */
    public function findByRoza(Roza $roza): array
    {
        return $this->findBy(['roza' => $roza]);
    }

    /**
     * Uczestnicy danej róży posortowani po pozycji (nulle na końcu).
     *
     * @return Uczestnik[]
     */
    public function findByRozaOrdered(Roza $roza): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.roza = :roza')
            ->setParameter('roza', $roza)
            ->orderBy('CASE WHEN u.pozycja IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('u.pozycja', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Uczestnicy z przypisaną pozycją w danej róży, posortowani po pozycji.
     *
     * @return Uczestnik[]
     */
    public function findAssignedByRoza(Roza $roza): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.roza = :roza')
            ->andWhere('u.pozycja IS NOT NULL')
            ->setParameter('roza', $roza)
            ->orderBy('u.pozycja', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Wszystkie róże, w których dany user uczestniczy.
     *
     * @return Uczestnik[]
     */
    public function findByUser(RozaniecUserInterface $user): array
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.roza', 'r')
            ->addSelect('r')
            ->where('u.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    public function findByRozaAndPozycja(Roza $roza, int $pozycja): ?Uczestnik
    {
        return $this->findOneBy(['roza' => $roza, 'pozycja' => $pozycja]);
    }

    public function findByRozaAndUser(Roza $roza, RozaniecUserInterface $user): ?Uczestnik
    {
        return $this->findOneBy(['roza' => $roza, 'user' => $user]);
    }
}
