<?php

namespace Rozaniec\RozaniecBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Rozaniec\RozaniecBundle\Entity\Czesc;
use Rozaniec\RozaniecBundle\Entity\Kolejnosc;
use Rozaniec\RozaniecBundle\Entity\Tajemnica;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'rozaniec:install',
    description: 'Instaluje dane referencyjne: części, kolejności i 20 tajemnic różańcowych',
)]
class RozaniecInstallCommand extends Command
{
    private const TAJEMNICE = [
        'Część I Różańca Świętego - Tajemnice Radosne' => [
            'Zwiastowanie Najświętszej Maryi Pannie.',
            'Nawiedzenie św. Elżbiety.',
            'Narodzenie Pana Jezusa.',
            'Ofiarowanie Pana Jezusa w świątyni.',
            'Odnalezienie Pana Jezusa w świątyni.',
        ],
        'Część II Różańca Świętego - Tajemnice Światła' => [
            'Chrzest Pana Jezusa w Jordanie.',
            'Cud w Kanie Galilejskiej.',
            'Głoszenie Królestwa Bożego i wzywanie do nawrócenia.',
            'Przemienienie na górze Tabor.',
            'Ustanowienie Eucharystii.',
        ],
        'Część III Różańca Świętego - Tajemnice Bolesne' => [
            'Modlitwa Pana Jezusa w Ogrójcu.',
            'Biczowanie Pana Jezusa.',
            'Cierniem ukoronowanie Pana Jezusa.',
            'Droga Krzyżowa Pana Jezusa.',
            'Ukrzyżowanie i Śmierć Pana Jezusa.',
        ],
        'Część IV Różańca Świętego - Tajemnice Chwalebne' => [
            'Zmartwychwstanie Pana Jezusa.',
            'Wniebowstąpienie Pana Jezusa.',
            'Zesłanie Ducha Świętego.',
            'Wniebowzięcie Najświętszej Maryi Panny.',
            'Ukoronowanie Najświętszej Maryi Panny na Królową Nieba i Ziemi.',
        ],
    ];

    public function __construct(
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Nadpisz istniejące dane');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        $existingCount = $this->em->getRepository(Tajemnica::class)->count([]);
        if ($existingCount > 0 && !$force) {
            $io->warning('Dane już istnieją (' . $existingCount . ' tajemnic). Użyj --force aby nadpisać.');
            return Command::FAILURE;
        }

        if ($force && $existingCount > 0) {
            $io->note('Usuwanie istniejących danych...');
            $this->em->createQuery('DELETE FROM ' . Tajemnica::class)->execute();
            $this->em->createQuery('DELETE FROM ' . Kolejnosc::class)->execute();
            $this->em->createQuery('DELETE FROM ' . Czesc::class)->execute();
        }

        // Kolejności (1-5)
        $kolejnosci = [];
        for ($i = 1; $i <= 5; $i++) {
            $k = new Kolejnosc();
            $k->setNumer($i);
            $this->em->persist($k);
            $kolejnosci[$i] = $k;
        }

        $io->success('Utworzono 5 kolejności.');

        // Części i tajemnice
        $pozycja = 1;
        foreach (self::TAJEMNICE as $czescNazwa => $nazwyTajemnic) {
            $czesc = new Czesc();
            $czesc->setNazwa($czescNazwa);
            $this->em->persist($czesc);

            $numer = 1;
            foreach ($nazwyTajemnic as $nazwaTajemnicy) {
                $tajemnica = new Tajemnica();
                $tajemnica->setNazwa($nazwaTajemnicy);
                $tajemnica->setCzesc($czesc);
                $tajemnica->setKolejnosc($kolejnosci[$numer]);
                $tajemnica->setPozycja($pozycja);
                $this->em->persist($tajemnica);

                $numer++;
                $pozycja++;
            }
        }

        $this->em->flush();

        $io->success('Utworzono 4 części i 20 tajemnic (dane referencyjne).');
        $io->note('Aby rozpocząć, utwórz nową różę w panelu administracyjnym lub użyj komendy rozaniec:migrate-to-multi.');

        return Command::SUCCESS;
    }
}
