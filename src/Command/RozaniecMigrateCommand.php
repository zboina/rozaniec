<?php

namespace Rozaniec\RozaniecBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Rozaniec\RozaniecBundle\Entity\Roza;
use Rozaniec\RozaniecBundle\Entity\Uczestnik;
use Rozaniec\RozaniecBundle\Entity\RozaniecUserProfile;
use Rozaniec\RozaniecBundle\Repository\RozaniecConfigRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'rozaniec:migrate-to-multi',
    description: 'Migruje dane z jednorożowego schematu do multi-róży (jednorazowa)',
)]
class RozaniecMigrateCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private RozaniecConfigRepository $configRepo,
        private string $userClass,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Sprawdź czy migracja potrzebna — jeśli istnieją już róże, skip
        $existingRoze = $this->em->getRepository(Roza::class)->count([]);
        if ($existingRoze > 0) {
            $io->warning('Migracja nie jest potrzebna — istnieją już róże (' . $existingRoze . ').');
            return Command::FAILURE;
        }

        // 1. Utwórz domyślną różę
        $roza = new Roza();
        $roza->setNazwa('Róża domyślna');

        // Przenieś ostatniaRotacja z configa
        $ostatnia = $this->configRepo->get('ostatnia_rotacja');
        if ($ostatnia) {
            $roza->setOstatniaRotacja($ostatnia);
        }

        $this->em->persist($roza);
        $this->em->flush();

        $io->success('Utworzono domyślną różę (ID: ' . $roza->getId() . ')');

        // 2. Pobierz stare przypisania z tabeli tajemnica (kolumna user_id)
        // Musimy użyć raw SQL bo pole user zostało usunięte z encji
        $conn = $this->em->getConnection();

        $hasUserColumn = false;
        try {
            $columns = $conn->executeQuery('SHOW COLUMNS FROM rozaniec_tajemnica LIKE \'user_id\'')->fetchAllAssociative();
            $hasUserColumn = count($columns) > 0;
        } catch (\Throwable) {
            // Kolumna nie istnieje
        }

        $migratedCount = 0;

        if ($hasUserColumn) {
            $rows = $conn->executeQuery(
                'SELECT t.pozycja, t.user_id FROM rozaniec_tajemnica t WHERE t.user_id IS NOT NULL ORDER BY t.pozycja'
            )->fetchAllAssociative();

            $io->note('Znaleziono ' . count($rows) . ' przypisań do migracji.');

            // Pobierz profile (jeśli tabela istnieje)
            $profilesByUserId = [];
            try {
                $profiles = $this->em->getRepository(RozaniecUserProfile::class)->findAll();
                foreach ($profiles as $p) {
                    $profilesByUserId[$p->getUserId()] = $p;
                }
            } catch (\Throwable) {
                // Tabela profili nie istnieje
            }

            // Pobierz userów
            $userRepo = $this->em->getRepository($this->userClass);

            foreach ($rows as $row) {
                $userId = $row['user_id'];
                $pozycja = $row['pozycja'];

                $user = $userRepo->find($userId);
                if (!$user) {
                    $io->warning('User #' . $userId . ' nie znaleziony — pomijam.');
                    continue;
                }

                $uczestnik = new Uczestnik();
                $uczestnik->setRoza($roza);
                $uczestnik->setPozycja((int) $pozycja);
                $uczestnik->setUser($user);

                // Dane z profilu
                $profile = $profilesByUserId[$userId] ?? null;
                if ($profile) {
                    $uczestnik->setFirstName($profile->getFirstName() ?? $this->resolveField($user, 'firstName') ?? 'Uczestnik');
                    $uczestnik->setLastName($profile->getLastName() ?? $this->resolveField($user, 'lastName') ?? '');
                    $uczestnik->setTelefon($profile->getTelefon());
                    $uczestnik->setNotifyChannels($profile->getNotifyChannels());
                } else {
                    $uczestnik->setFirstName($this->resolveField($user, 'firstName') ?? 'Uczestnik');
                    $uczestnik->setLastName($this->resolveField($user, 'lastName') ?? '');
                }

                // Email z usera
                if (method_exists($user, 'getEmail') && $user->getEmail()) {
                    $uczestnik->setEmail($user->getEmail());
                }

                $this->em->persist($uczestnik);
                $migratedCount++;
            }

            $this->em->flush();
        } else {
            $io->note('Kolumna user_id nie istnieje w rozaniec_tajemnica — brak danych do migracji.');
        }

        $io->success(sprintf(
            'Migracja zakończona: róża "%s", %d uczestników zmigrowanych.',
            $roza->getNazwa(),
            $migratedCount
        ));

        $io->note('Następne kroki:');
        $io->listing([
            'Utwórz migrację Doctrine aby dodać nowe tabele i usunąć kolumnę user_id z rozaniec_tajemnica',
            'Opcjonalnie: usuń tabelę rozaniec_user_profile po weryfikacji',
            'Zmień nazwę domyślnej róży na właściwą',
        ]);

        return Command::SUCCESS;
    }

    private function resolveField(object $user, string $field): ?string
    {
        $getter = 'get' . ucfirst($field);
        if (method_exists($user, $getter)) {
            return $user->$getter();
        }
        return null;
    }
}
