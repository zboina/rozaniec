<?php

namespace Rozaniec\RozaniecBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Rozaniec\RozaniecBundle\Entity\Tajemnica;
use Rozaniec\RozaniecBundle\Repository\RozaniecConfigRepository;
use Rozaniec\RozaniecBundle\Repository\TajemnicaRepository;

class RotacjaService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TajemnicaRepository $tajemnicaRepo,
        private RozaniecConfigRepository $configRepo,
    ) {
    }

    /**
     * Sprawdza czy potrzebna rotacja, jeśli tak — wykonuje (lazy, raz na miesiąc).
     */
    public function ensureRotated(): void
    {
        $ostatnia = $this->configRepo->get('ostatnia_rotacja');

        $now = new \DateTimeImmutable();
        $currentMonth = $now->format('Y-m');

        // Pierwsze uruchomienie — ustaw na bieżący miesiąc
        if ($ostatnia === null) {
            $this->configRepo->set('ostatnia_rotacja', $currentMonth);
            return;
        }

        // Oblicz różnicę miesięcy
        $ostatniaDate = \DateTimeImmutable::createFromFormat('Y-m', $ostatnia);
        if ($ostatniaDate === false) {
            $this->configRepo->set('ostatnia_rotacja', $currentMonth);
            return;
        }

        $monthsDiff = ($now->format('Y') - $ostatniaDate->format('Y')) * 12
            + ($now->format('n') - $ostatniaDate->format('n'));

        if ($monthsDiff <= 0) {
            return; // Bez zmian — ten sam miesiąc
        }

        $steps = $monthsDiff % 20;
        if ($steps > 0) {
            $this->rotate($steps);
        }

        $this->configRepo->set('ostatnia_rotacja', $currentMonth);
    }

    /**
     * Przesuwa userów o N pozycji cyklicznie w obrębie 20 tajemnic.
     */
    private function rotate(int $steps): void
    {
        $tajemnice = $this->tajemnicaRepo->findAllOrdered();

        if (count($tajemnice) === 0) {
            return;
        }

        $total = count($tajemnice);

        // Zbierz obecne przypisania: pozycja → user
        $userByPozycja = [];
        foreach ($tajemnice as $t) {
            $userByPozycja[$t->getPozycja()] = $t->getUser();
        }

        // Przesuń: user z pozycji P idzie do pozycji ((P + steps - 1) % total) + 1
        $newAssignments = [];
        foreach ($userByPozycja as $pozycja => $user) {
            $newPozycja = (($pozycja - 1 + $steps) % $total) + 1;
            $newAssignments[$newPozycja] = $user;
        }

        // Zastosuj nowe przypisania
        foreach ($tajemnice as $t) {
            $t->setUser($newAssignments[$t->getPozycja()] ?? null);
        }

        $this->em->flush();
    }

    /**
     * Zwraca wszystkie tajemnice z aktualnymi przypisaniami (po rotacji).
     *
     * @return Tajemnica[]
     */
    public function getTajemnice(): array
    {
        return $this->tajemnicaRepo->findAllOrdered();
    }
}
