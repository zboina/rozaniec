<?php

namespace Rozaniec\RozaniecBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Rozaniec\RozaniecBundle\Entity\Roza;
use Rozaniec\RozaniecBundle\Entity\Tajemnica;
use Rozaniec\RozaniecBundle\Entity\Uczestnik;
use Rozaniec\RozaniecBundle\Repository\TajemnicaRepository;
use Rozaniec\RozaniecBundle\Repository\UczestnikRepository;

class RotacjaService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TajemnicaRepository $tajemnicaRepo,
        private UczestnikRepository $uczestnikRepo,
    ) {
    }

    /**
     * Sprawdza czy potrzebna rotacja dla danej róży, jeśli tak — wykonuje.
     *
     * @return bool true jeśli wykonano rotację
     */
    public function ensureRotated(Roza $roza): bool
    {
        $ostatnia = $roza->getOstatniaRotacja();

        $now = new \DateTimeImmutable();
        $currentMonth = $now->format('Y-m');

        if ($ostatnia === null) {
            $roza->setOstatniaRotacja($currentMonth);
            $this->em->flush();
            return false;
        }

        $ostatniaDate = \DateTimeImmutable::createFromFormat('Y-m', $ostatnia);
        if ($ostatniaDate === false) {
            $roza->setOstatniaRotacja($currentMonth);
            $this->em->flush();
            return false;
        }

        $monthsDiff = ($now->format('Y') - $ostatniaDate->format('Y')) * 12
            + ($now->format('n') - $ostatniaDate->format('n'));

        if ($monthsDiff <= 0) {
            return false;
        }

        $steps = $monthsDiff % 20;
        if ($steps > 0) {
            $this->rotate($roza, $steps);
        }

        $roza->setOstatniaRotacja($currentMonth);
        $this->em->flush();
        return true;
    }

    /**
     * Przesuwa uczestników róży o N pozycji cyklicznie w obrębie 20 tajemnic.
     */
    public function rotate(Roza $roza, int $steps): void
    {
        $uczestnicy = $this->uczestnikRepo->findAssignedByRoza($roza);

        if (count($uczestnicy) === 0) {
            return;
        }

        $total = 20;

        // Normalizuj kroki
        $steps = (($steps % $total) + $total) % $total;
        if ($steps === 0) {
            return;
        }

        // Przesuń: uczestnik z pozycji P idzie do pozycji ((P + steps - 1) % total) + 1
        foreach ($uczestnicy as $uczestnik) {
            $staraPozycja = $uczestnik->getPozycja();
            $nowaPozycja = (($staraPozycja - 1 + $steps) % $total) + 1;
            $uczestnik->setPozycja($nowaPozycja);
        }

        $this->em->flush();
    }

    /**
     * Zwraca pary [uczestnik, tajemnica] na podstawie dopasowania pozycji.
     *
     * @return array<int, array{uczestnik: Uczestnik, tajemnica: Tajemnica}>
     */
    public function getUczestnicyWithTajemnice(Roza $roza): array
    {
        $tajemnice = $this->tajemnicaRepo->findAllOrdered();
        $uczestnicy = $this->uczestnikRepo->findAssignedByRoza($roza);

        // Indeksuj uczestników po pozycji
        $uczByPozycja = [];
        foreach ($uczestnicy as $u) {
            $uczByPozycja[$u->getPozycja()] = $u;
        }

        $result = [];
        foreach ($tajemnice as $tajemnica) {
            $poz = $tajemnica->getPozycja();
            $result[$poz] = [
                'uczestnik' => $uczByPozycja[$poz] ?? null,
                'tajemnica' => $tajemnica,
            ];
        }

        return $result;
    }

    /**
     * @return Tajemnica[]
     */
    public function getTajemnice(): array
    {
        return $this->tajemnicaRepo->findAllOrdered();
    }
}
