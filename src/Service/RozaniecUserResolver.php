<?php

namespace Rozaniec\RozaniecBundle\Service;

use Rozaniec\RozaniecBundle\Entity\Roza;
use Rozaniec\RozaniecBundle\Entity\Uczestnik;
use Rozaniec\RozaniecBundle\Model\RozaniecUserInterface;
use Rozaniec\RozaniecBundle\Repository\UczestnikRepository;

class RozaniecUserResolver
{
    /**
     * @param string[] $fullNameFields
     */
    public function __construct(
        private UczestnikRepository $uczestnikRepo,
        private array $fullNameFields = ['firstName', 'lastName'],
    ) {
    }

    /**
     * Szuka uczestnika w danej róży po userze Symfony.
     */
    public function getUczestnik(Roza $roza, RozaniecUserInterface $user): ?Uczestnik
    {
        return $this->uczestnikRepo->findByRozaAndUser($roza, $user);
    }

    /**
     * Zwraca pełną nazwę na podstawie obiektu User (dla wyświetlania poza kontekstem Uczestnika).
     */
    public function getFullName(object $user): string
    {
        if (method_exists($user, 'getFullName') && $user->getFullName()) {
            return $user->getFullName();
        }

        $parts = [];
        foreach ($this->fullNameFields as $field) {
            $getter = 'get' . ucfirst($field);
            if (method_exists($user, $getter)) {
                $val = $user->$getter();
                if ($val) {
                    $parts[] = $val;
                }
            } elseif (property_exists($user, $field)) {
                $ref = new \ReflectionProperty($user, $field);
                $val = $ref->getValue($user);
                if ($val) {
                    $parts[] = $val;
                }
            }
        }

        if ($parts) {
            return implode(' ', $parts);
        }

        if (method_exists($user, 'getUserIdentifier')) {
            return $user->getUserIdentifier();
        }

        return '?';
    }

    /**
     * Zwraca wszystkie uczestnictwa danego usera (we wszystkich różach).
     *
     * @return Uczestnik[]
     */
    public function getUczestnicyForUser(RozaniecUserInterface $user): array
    {
        return $this->uczestnikRepo->findByUser($user);
    }
}
