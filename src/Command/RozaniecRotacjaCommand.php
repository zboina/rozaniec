<?php

namespace Rozaniec\RozaniecBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Rozaniec\RozaniecBundle\Repository\RozaRepository;
use Rozaniec\RozaniecBundle\Service\RotacjaService;
use Rozaniec\RozaniecBundle\Service\RozaniecNotifier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'rozaniec:rotacja',
    description: 'Wykonuje automatyczną rotację róż wg ich trybu (cron)',
)]
class RozaniecRotacjaCommand extends Command
{
    public function __construct(
        private RozaRepository $rozaRepo,
        private RotacjaService $rotacjaService,
        private RozaniecNotifier $rozaniecNotifier,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Podgląd — nie wykonuje zmian');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $io->note('Tryb dry-run — żadne zmiany nie zostaną zapisane.');
        }

        $now = new \DateTimeImmutable();
        $currentMonth = $now->format('Y-m');
        $day = (int) $now->format('j');
        $dayOfWeek = (int) $now->format('N'); // 1=poniedziałek, 7=niedziela

        $roze = $this->rozaRepo->findAll();

        if (count($roze) === 0) {
            $io->warning('Brak róż w bazie.');
            return Command::SUCCESS;
        }

        $rows = [];
        $rotated = 0;

        foreach ($roze as $roza) {
            $tryb = $roza->getRotacjaTryb();
            $ostatnia = $roza->getOstatniaRotacja();

            // Warunek 1: jeszcze nie rotowana w tym miesiącu
            $alreadyRotated = ($ostatnia === $currentMonth);

            // Warunek 2: dziś jest dniem rotacji wg trybu
            $isDayMatch = match ($tryb) {
                'pierwsza_niedziela' => $dayOfWeek === 7 && $day <= 7,
                'wybrany_dzien' => $day === ($roza->getRotacjaDzien() ?? 1),
                default => $day === 1, // 'pierwszy_dzien'
            };

            $trybLabel = match ($tryb) {
                'pierwsza_niedziela' => '1. niedziela',
                'wybrany_dzien' => $roza->getRotacjaDzien() . '. dzień',
                default => '1. dzień',
            };

            if ($alreadyRotated) {
                $rows[] = [$roza->getNazwa(), $trybLabel, $ostatnia, '<comment>już rotowana</comment>'];
                continue;
            }

            if (!$isDayMatch) {
                $rows[] = [$roza->getNazwa(), $trybLabel, $ostatnia ?? '—', '<comment>nie dziś</comment>'];
                continue;
            }

            // Oba warunki spełnione — rotuj
            if ($dryRun) {
                $rows[] = [$roza->getNazwa(), $trybLabel, $ostatnia ?? '—', '<info>DO ROTACJI (dry-run)</info>'];
            } else {
                $this->rotacjaService->rotate($roza, 1);
                $roza->setOstatniaRotacja($currentMonth);
                $this->em->flush();

                $this->rozaniecNotifier->notifyAllAfterRotation($roza);

                $rows[] = [$roza->getNazwa(), $trybLabel, $currentMonth, '<info>ROTACJA WYKONANA</info>'];
                $rotated++;
            }
        }

        $io->table(['Róża', 'Tryb', 'Ostatnia rotacja', 'Status'], $rows);

        if ($dryRun) {
            $io->info('Dry-run zakończony — nic nie zmieniono.');
        } else {
            $io->success('Gotowe. Rotacji wykonanych: ' . $rotated . '.');
        }

        // ===== PRZYPOMNIENIE ZELATORA =====
        $tomorrow = $now->modify('+1 day');
        $tomorrowDay = (int) $tomorrow->format('j');
        $tomorrowDow = (int) $tomorrow->format('N'); // 7=niedziela

        $isFirstSunday = ($tomorrowDow === 7 && $tomorrowDay <= 7);

        if ($isFirstSunday) {
            $io->section('Przypomnienie zelatora (jutro = 1. niedziela)');

            $zelatorRows = [];
            foreach ($roze as $roza) {
                $zelator = $roza->getZelator();
                if (!$zelator) {
                    $zelatorRows[] = [$roza->getNazwa(), '—', '—', '<comment>brak zelatora</comment>'];
                    continue;
                }

                $phone = $zelator->getTelefon();
                if (!$phone) {
                    $zelatorRows[] = [$roza->getNazwa(), $zelator->getFullName(), '—', '<comment>brak telefonu</comment>'];
                    continue;
                }

                if ($dryRun) {
                    $zelatorRows[] = [$roza->getNazwa(), $zelator->getFullName(), $phone, '<info>SMS (dry-run)</info>'];
                } else {
                    $sent = $this->rozaniecNotifier->notifyZelatorReminder($roza);
                    $zelatorRows[] = [$roza->getNazwa(), $zelator->getFullName(), $phone, $sent ? '<info>SMS wysłany</info>' : '<error>błąd wysyłki</error>'];
                }
            }

            $io->table(['Róża', 'Zelator', 'Telefon', 'Status'], $zelatorRows);
        }

        return Command::SUCCESS;
    }
}
