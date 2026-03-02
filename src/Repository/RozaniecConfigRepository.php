<?php

namespace Rozaniec\RozaniecBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Rozaniec\RozaniecBundle\Entity\RozaniecConfig;

/**
 * @extends ServiceEntityRepository<RozaniecConfig>
 */
class RozaniecConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RozaniecConfig::class);
    }

    public function get(string $klucz): ?string
    {
        $config = $this->findOneBy(['klucz' => $klucz]);
        return $config?->getWartosc();
    }

    public function set(string $klucz, string $wartosc): void
    {
        $config = $this->findOneBy(['klucz' => $klucz]);
        if (!$config) {
            $config = new RozaniecConfig();
            $config->setKlucz($klucz);
            $this->getEntityManager()->persist($config);
        }
        $config->setWartosc($wartosc);
        $this->getEntityManager()->flush();
    }
}
