<?php

namespace Rozaniec\RozaniecBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Rozaniec\RozaniecBundle\Entity\Roza;
use Rozaniec\RozaniecBundle\Entity\Uczestnik;
use Rozaniec\RozaniecBundle\Model\RozaniecUserInterface;
use Rozaniec\RozaniecBundle\Repository\RozaRepository;
use Rozaniec\RozaniecBundle\Repository\RozaniecConfigRepository;
use Rozaniec\RozaniecBundle\Repository\TajemnicaRepository;
use Rozaniec\RozaniecBundle\Repository\UczestnikRepository;
use Rozaniec\RozaniecBundle\Service\RotacjaService;
use Rozaniec\RozaniecBundle\Service\RozaniecNotifier;
use Rozaniec\RozaniecBundle\Service\RozaniecUserResolver;
use SerwerSMS\SerwerSMS;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rozaniec')]
#[IsGranted('ROLE_USER')]
class RozaniecController extends AbstractController
{
    public function __construct(
        private RotacjaService $rotacjaService,
        private EntityManagerInterface $em,
        private TajemnicaRepository $tajemnicaRepo,
        private RozaRepository $rozaRepo,
        private UczestnikRepository $uczestnikRepo,
        private RozaniecUserResolver $userResolver,
        private RozaniecNotifier $rozaniecNotifier,
        private RozaniecConfigRepository $configRepo,
    ) {
    }

    // ========== USER VIEWS ==========

    /**
     * Dashboard — lista róż użytkownika.
     */
    #[Route('/', name: 'rozaniec_index')]
    public function index(): Response
    {
        /** @var RozaniecUserInterface $currentUser */
        $currentUser = $this->getUser();

        // Pobierz róże, w których user uczestniczy
        $mojeUczestnictwa = $this->userResolver->getUczestnicyForUser($currentUser);

        // Dla każdego uczestnictwa, wykonaj auto-rotację i pobierz tajemnicę
        $rozaData = [];
        foreach ($mojeUczestnictwa as $uczestnik) {
            $roza = $uczestnik->getRoza();
            $rotated = $this->rotacjaService->ensureRotated($roza);
            if ($rotated) {
                $this->rozaniecNotifier->notifyAllAfterRotation($roza);
                // Odśwież uczestnika po rotacji
                $this->em->refresh($uczestnik);
            }

            $mojaTajemnica = null;
            if ($uczestnik->getPozycja()) {
                $tajemnice = $this->rotacjaService->getTajemnice();
                foreach ($tajemnice as $t) {
                    if ($t->getPozycja() === $uczestnik->getPozycja()) {
                        $mojaTajemnica = $t;
                        break;
                    }
                }
            }

            $rozaData[] = [
                'roza' => $roza,
                'uczestnik' => $uczestnik,
                'mojaTajemnica' => $mojaTajemnica,
            ];
        }

        // Pobierz też wszystkie róże (jeśli admin)
        $wszystkieRoze = $this->isGranted('ROLE_ADMIN') ? $this->rozaRepo->findAll() : [];

        return $this->render('@Rozaniec/dashboard.html.twig', [
            'rozaData' => $rozaData,
            'wszystkieRoze' => $wszystkieRoze,
        ]);
    }

    /**
     * Widok jednej róży (user view).
     */
    #[Route('/roza/{id}', name: 'rozaniec_roza_show')]
    public function rozaShow(Roza $roza): Response
    {
        $rotated = $this->rotacjaService->ensureRotated($roza);
        if ($rotated) {
            $this->rozaniecNotifier->notifyAllAfterRotation($roza);
        }

        $pairs = $this->rotacjaService->getUczestnicyWithTajemnice($roza);
        $tajemnice = $this->rotacjaService->getTajemnice();

        // Grupuj po częściach
        $grouped = [];
        foreach ($tajemnice as $t) {
            $czescNazwa = $t->getCzesc()->getNazwa();
            $grouped[$czescNazwa][] = [
                'tajemnica' => $t,
                'uczestnik' => $pairs[$t->getPozycja()]['uczestnik'] ?? null,
            ];
        }

        // Moja tajemnica w tej róży
        /** @var RozaniecUserInterface|null $currentUser */
        $currentUser = $this->getUser();
        $mojUczestnik = null;
        $mojaTajemnica = null;
        if ($currentUser) {
            $mojUczestnik = $this->uczestnikRepo->findByRozaAndUser($roza, $currentUser);
            if ($mojUczestnik && $mojUczestnik->getPozycja()) {
                foreach ($tajemnice as $t) {
                    if ($t->getPozycja() === $mojUczestnik->getPozycja()) {
                        $mojaTajemnica = $t;
                        break;
                    }
                }
            }
        }

        // Statystyki
        $assigned = 0;
        foreach ($pairs as $pair) {
            if ($pair['uczestnik']) {
                $assigned++;
            }
        }

        return $this->render('@Rozaniec/roza.html.twig', [
            'roza' => $roza,
            'grouped' => $grouped,
            'mojaTajemnica' => $mojaTajemnica,
            'mojUczestnik' => $mojUczestnik,
            'assignedCount' => $assigned,
            'totalCount' => count($tajemnice),
        ]);
    }

    /**
     * User toggle — zapisz się / wypisz się z pozycji w róży.
     */
    #[Route('/toggle/{rozaId}/{pozycja}', name: 'rozaniec_toggle', methods: ['POST'])]
    public function toggle(int $rozaId, int $pozycja, Request $request): Response
    {
        $roza = $this->rozaRepo->find($rozaId);
        if (!$roza) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('rozaniec_toggle_' . $rozaId . '_' . $pozycja, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Nieprawidłowy token CSRF.');
            return $this->redirectToRoute('rozaniec_roza_show', ['id' => $rozaId]);
        }

        /** @var RozaniecUserInterface $currentUser */
        $currentUser = $this->getUser();
        $uczestnik = $this->uczestnikRepo->findByRozaAndUser($roza, $currentUser);

        if ($uczestnik && $uczestnik->getPozycja() === $pozycja) {
            // Odpisanie — user rezygnuje ze swojej pozycji
            $uczestnik->setPozycja(null);
        } elseif ($uczestnik && $uczestnik->getPozycja() !== null) {
            // User ma już inną pozycję
            $this->addFlash('danger', 'Masz już przypisaną pozycję w tej róży.');
            return $this->redirectToRoute('rozaniec_roza_show', ['id' => $rozaId]);
        } else {
            // Sprawdź czy pozycja wolna
            $existing = $this->uczestnikRepo->findByRozaAndPozycja($roza, $pozycja);
            if ($existing) {
                $this->addFlash('danger', 'Ta pozycja jest już zajęta.');
                return $this->redirectToRoute('rozaniec_roza_show', ['id' => $rozaId]);
            }

            if ($uczestnik) {
                // Uczestnik bez pozycji — przypisz
                $uczestnik->setPozycja($pozycja);
            } else {
                // User nie jest jeszcze uczestnikiem — utwórz
                $uczestnik = new Uczestnik();
                $uczestnik->setRoza($roza);
                $uczestnik->setUser($currentUser);
                $uczestnik->setFirstName($this->resolveFirstName($currentUser));
                $uczestnik->setLastName($this->resolveLastName($currentUser));
                if (method_exists($currentUser, 'getEmail')) {
                    $uczestnik->setEmail($currentUser->getEmail());
                }
                $uczestnik->setPozycja($pozycja);
                $this->em->persist($uczestnik);
            }
        }

        $this->em->flush();

        return $this->redirectToRoute('rozaniec_roza_show', ['id' => $rozaId]);
    }

    // ========== ADMIN VIEWS ==========

    /**
     * Admin — lista róż.
     */
    #[Route('/admin/', name: 'rozaniec_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminIndex(): Response
    {
        $roze = $this->rozaRepo->findAll();

        $stats = [];
        foreach ($roze as $roza) {
            $uczestnicy = $this->uczestnikRepo->findByRoza($roza);
            $assigned = 0;
            foreach ($uczestnicy as $u) {
                if ($u->getPozycja() !== null) {
                    $assigned++;
                }
            }
            $stats[$roza->getId()] = [
                'total' => count($uczestnicy),
                'assigned' => $assigned,
            ];
        }

        return $this->render('@Rozaniec/admin/index.html.twig', [
            'roze' => $roze,
            'stats' => $stats,
        ]);
    }

    /**
     * Admin — tworzenie nowej róży.
     */
    #[Route('/admin/roza/nowa', name: 'rozaniec_admin_roza_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminRozaCreate(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $nazwa = trim($request->request->get('nazwa', ''));
            if ($nazwa === '') {
                $this->addFlash('danger', 'Nazwa róży jest wymagana.');
                return $this->redirectToRoute('rozaniec_admin_roza_create');
            }

            $roza = new Roza();
            $roza->setNazwa($nazwa);
            $roza->setOstatniaRotacja((new \DateTimeImmutable())->format('Y-m'));
            $this->em->persist($roza);
            $this->em->flush();

            $this->addFlash('success', 'Róża "' . $nazwa . '" została utworzona.');
            return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
        }

        return $this->render('@Rozaniec/admin/roza_nowa.html.twig');
    }

    /**
     * Admin — zarządzanie jedną różą.
     */
    #[Route('/admin/roza/{id}', name: 'rozaniec_admin_roza')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminRoza(Roza $roza): Response
    {
        $this->rotacjaService->ensureRotated($roza);

        $pairs = $this->rotacjaService->getUczestnicyWithTajemnice($roza);
        $tajemnice = $this->rotacjaService->getTajemnice();
        $uczestnicy = $this->uczestnikRepo->findByRozaOrdered($roza);

        // Grupuj tajemnice po częściach
        $grouped = [];
        foreach ($tajemnice as $t) {
            $czescNazwa = $t->getCzesc()->getNazwa();
            $grouped[$czescNazwa][] = [
                'tajemnica' => $t,
                'uczestnik' => $pairs[$t->getPozycja()]['uczestnik'] ?? null,
            ];
        }

        // Statystyki
        $assigned = 0;
        foreach ($uczestnicy as $u) {
            if ($u->getPozycja() !== null) {
                $assigned++;
            }
        }

        // Userzy z konta Symfony (do dropdownu)
        $userClass = $this->getParameter('rozaniec.user_class');
        $users = $this->em->getRepository($userClass)->findAll();

        // Zbierz IDs userów już przypisanych do tej róży
        $assignedUserIds = [];
        foreach ($uczestnicy as $u) {
            if ($u->getUser()) {
                $assignedUserIds[] = $u->getUser()->getId();
            }
        }

        return $this->render('@Rozaniec/admin/roza.html.twig', [
            'roza' => $roza,
            'grouped' => $grouped,
            'uczestnicy' => $uczestnicy,
            'assignedCount' => $assigned,
            'totalCount' => count($tajemnice),
            'users' => $users,
            'assignedUserIds' => $assignedUserIds,
        ]);
    }

    /**
     * Admin — dodaj uczestnika do róży.
     */
    #[Route('/admin/roza/{id}/uczestnik', name: 'rozaniec_admin_add_uczestnik', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminAddUczestnik(Roza $roza, Request $request): Response
    {
        $firstName = trim($request->request->get('firstName', ''));
        $lastName = trim($request->request->get('lastName', ''));

        if ($firstName === '' || $lastName === '') {
            $this->addFlash('danger', 'Imię i nazwisko są wymagane.');
            return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
        }

        $uczestnik = new Uczestnik();
        $uczestnik->setRoza($roza);
        $uczestnik->setFirstName($firstName);
        $uczestnik->setLastName($lastName);

        $email = trim($request->request->get('email', ''));
        if ($email !== '') {
            $uczestnik->setEmail($email);
        }

        $telefon = trim($request->request->get('telefon', ''));
        if ($telefon !== '') {
            $uczestnik->setTelefon($telefon);
        }

        // Opcjonalnie powiąż z kontem Symfony
        $userId = $request->request->get('user_id');
        if ($userId) {
            $userClass = $this->getParameter('rozaniec.user_class');
            $user = $this->em->getRepository($userClass)->find($userId);
            if ($user) {
                // Sprawdź czy user nie jest już w tej róży
                $existing = $this->uczestnikRepo->findByRozaAndUser($roza, $user);
                if ($existing) {
                    $this->addFlash('danger', 'Ten użytkownik jest już uczestnikiem tej róży.');
                    return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
                }
                $uczestnik->setUser($user);
            }
        }

        $this->em->persist($uczestnik);
        $this->em->flush();

        $this->addFlash('success', 'Uczestnik ' . $uczestnik->getFullName() . ' został dodany.');
        return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
    }

    /**
     * Admin — przypisz uczestnika do pozycji.
     */
    #[Route('/admin/roza/{id}/assign/{uczestnikId}', name: 'rozaniec_admin_assign', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminAssign(Roza $roza, int $uczestnikId, Request $request): Response
    {
        $uczestnik = $this->uczestnikRepo->find($uczestnikId);
        if (!$uczestnik || $uczestnik->getRoza()->getId() !== $roza->getId()) {
            throw $this->createNotFoundException();
        }

        $pozycja = $request->request->get('pozycja');

        if ($pozycja === '' || $pozycja === null) {
            // Usuń przypisanie
            $uczestnik->setPozycja(null);
        } else {
            $pozycja = (int) $pozycja;
            if ($pozycja < 1 || $pozycja > 20) {
                $this->addFlash('danger', 'Pozycja musi być między 1 a 20.');
                return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
            }

            // Sprawdź czy pozycja wolna
            $existing = $this->uczestnikRepo->findByRozaAndPozycja($roza, $pozycja);
            if ($existing && $existing->getId() !== $uczestnik->getId()) {
                $this->addFlash('danger', 'Pozycja ' . $pozycja . ' jest już zajęta przez ' . $existing->getFullName() . '.');
                return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
            }

            $uczestnik->setPozycja($pozycja);
        }

        $this->em->flush();
        return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
    }

    /**
     * Admin — zapisz tryb automatycznej rotacji.
     */
    #[Route('/admin/roza/{id}/rotacja-tryb', name: 'rozaniec_admin_rotacja_tryb', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminSaveRotacjaTryb(Roza $roza, Request $request): Response
    {
        $tryb = $request->request->get('rotacja_tryb', 'pierwszy_dzien');

        if (!in_array($tryb, ['pierwszy_dzien', 'pierwsza_niedziela', 'wybrany_dzien'], true)) {
            $this->addFlash('danger', 'Nieprawidłowy tryb rotacji.');
            return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
        }

        $roza->setRotacjaTryb($tryb);

        if ($tryb === 'wybrany_dzien') {
            $dzien = (int) $request->request->get('rotacja_dzien', 1);
            if ($dzien < 1 || $dzien > 31) {
                $this->addFlash('danger', 'Dzień musi być między 1 a 31.');
                return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
            }
            $roza->setRotacjaDzien($dzien);
        } else {
            $roza->setRotacjaDzien(null);
        }

        $this->em->flush();

        $label = match ($tryb) {
            'pierwsza_niedziela' => '1. niedziela miesiąca',
            'wybrany_dzien' => $roza->getRotacjaDzien() . '. dzień miesiąca',
            default => '1. dzień miesiąca',
        };
        $this->addFlash('success', 'Tryb rotacji zmieniony na: ' . $label . '.');

        return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
    }

    /**
     * Admin — ręczna rotacja per róża.
     */
    #[Route('/admin/roza/{id}/rotacja', name: 'rozaniec_admin_rotacja', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminRotacja(Roza $roza, Request $request): Response
    {
        $direction = $request->request->get('direction');
        $steps = (int) $request->request->get('steps', 1);

        if ($steps < 1 || $steps > 19) {
            $this->addFlash('danger', 'Liczba kroków musi być między 1 a 19.');
            return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
        }

        if ($direction === 'back') {
            $steps = -$steps;
        }

        $this->rotacjaService->rotate($roza, $steps);

        $sendNotify = $request->request->getBoolean('send_notifications');
        if ($sendNotify) {
            $this->rozaniecNotifier->notifyAllAfterRotation($roza);
        }

        $label = abs($steps) === 1 ? 'pozycję' : 'pozycje';
        $dir = $steps > 0 ? 'do przodu' : 'do tyłu';
        $msg = 'Rotacja wykonana: ' . abs($steps) . ' ' . $label . ' ' . $dir . '.';
        if ($sendNotify) {
            $msg .= ' Powiadomienia wysłane.';
        }
        $this->addFlash('success', $msg);

        return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
    }

    /**
     * Admin — zapisz ustawienia powiadomień (globalnie).
     */
    #[Route('/admin/powiadomienia', name: 'rozaniec_admin_notify_save', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function notifySave(Request $request): Response
    {
        $enabled = $request->request->getBoolean('notify_enabled') ? '1' : '0';
        $this->configRepo->set('notify_enabled', $enabled);

        foreach (RozaniecNotifier::CHANNELS as $channel) {
            $key = 'notify_' . $channel . '_enabled';
            $val = $request->request->getBoolean($key) ? '1' : '0';
            $this->configRepo->set($key, $val);
        }

        $this->addFlash('success', 'Ustawienia powiadomień zapisane.');

        $redirect = $request->request->get('_redirect');
        if ($redirect) {
            return $this->redirect($redirect);
        }

        return $this->redirectToRoute('rozaniec_admin');
    }

    /**
     * Admin — zapisz dane uczestników róży.
     */
    #[Route('/admin/roza/{id}/uczestnicy', name: 'rozaniec_admin_uczestnicy_save', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function uczestnicySave(Roza $roza, Request $request): Response
    {
        $postedFirstName = $request->request->all('firstName') ?: [];
        $postedLastName = $request->request->all('lastName') ?: [];
        $postedEmail = $request->request->all('email') ?: [];
        $postedTelefon = $request->request->all('telefon') ?: [];
        $postedChannels = $request->request->all('channels') ?: [];
        $postedUserId = $request->request->all('user_id') ?: [];

        $userClass = $this->getParameter('rozaniec.user_class');
        $userRepo = $this->em->getRepository($userClass);

        $uczestnicy = $this->uczestnikRepo->findByRoza($roza);
        $uczById = [];
        foreach ($uczestnicy as $u) {
            $uczById[$u->getId()] = $u;
        }

        foreach ($postedFirstName as $uczId => $firstName) {
            if (!isset($uczById[$uczId])) {
                continue;
            }
            $ucz = $uczById[$uczId];

            $fn = trim($firstName);
            if ($fn !== '') {
                $ucz->setFirstName($fn);
            }

            $ln = trim($postedLastName[$uczId] ?? '');
            if ($ln !== '') {
                $ucz->setLastName($ln);
            }

            $email = trim($postedEmail[$uczId] ?? '');
            $ucz->setEmail($email !== '' ? $email : null);

            $tel = trim($postedTelefon[$uczId] ?? '');
            $ucz->setTelefon($tel !== '' ? $tel : null);

            $channels = $postedChannels[$uczId] ?? [];
            if (is_string($channels)) {
                $channels = [$channels];
            }
            $ucz->setNotifyChannels(array_values($channels));

            // Przypisanie / odłączenie konta Symfony
            $newUserId = $postedUserId[$uczId] ?? '';
            if ($newUserId === '' || $newUserId === '0') {
                $ucz->setUser(null);
            } else {
                $newUser = $userRepo->find($newUserId);
                if ($newUser) {
                    // Sprawdź czy ten user nie jest już przypisany do innego uczestnika w tej róży
                    $existing = $this->uczestnikRepo->findByRozaAndUser($roza, $newUser);
                    if (!$existing || $existing->getId() === $ucz->getId()) {
                        $ucz->setUser($newUser);
                    }
                }
            }
        }

        $this->em->flush();
        $this->addFlash('success', 'Dane uczestników zapisane.');

        return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
    }

    /**
     * Admin — wyślij powiadomienie o aktualnej tajemnicy do jednego uczestnika (email i/lub SMS).
     */
    #[Route('/admin/roza/{id}/uczestnik/{uczestnikId}/notify', name: 'rozaniec_admin_notify_uczestnik', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminNotifyUczestnik(Roza $roza, int $uczestnikId): Response
    {
        $uczestnik = $this->uczestnikRepo->find($uczestnikId);
        if (!$uczestnik || $uczestnik->getRoza()->getId() !== $roza->getId()) {
            throw $this->createNotFoundException();
        }

        if (!$uczestnik->getEmail() && !$uczestnik->getTelefon()) {
            $this->addFlash('danger', 'Uczestnik ' . $uczestnik->getFullName() . ' nie ma adresu email ani numeru telefonu.');
            return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
        }

        if (!$uczestnik->getPozycja()) {
            $this->addFlash('danger', 'Uczestnik ' . $uczestnik->getFullName() . ' nie ma przypisanej pozycji.');
            return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
        }

        try {
            $sent = $this->rozaniecNotifier->notifySingle($uczestnik, $roza);
            if ($sent) {
                $channels = $uczestnik->getEffectiveChannels();
                $info = implode(' + ', $channels);
                $this->addFlash('success', 'Powiadomienie wysłane do ' . $uczestnik->getFullName() . ' (' . $info . ').');
            } else {
                $this->addFlash('warning', 'Nie udało się wysłać powiadomienia — brak kanałów lub danych.');
            }
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Błąd wysyłki: ' . $e->getMessage());
        }

        return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
    }

    /**
     * Admin — usuń uczestnika.
     */
    #[Route('/admin/roza/{id}/uczestnik/{uczestnikId}/usun', name: 'rozaniec_admin_delete_uczestnik', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDeleteUczestnik(Roza $roza, int $uczestnikId, Request $request): Response
    {
        $uczestnik = $this->uczestnikRepo->find($uczestnikId);
        if (!$uczestnik || $uczestnik->getRoza()->getId() !== $roza->getId()) {
            throw $this->createNotFoundException();
        }

        $name = $uczestnik->getFullName();
        $this->em->remove($uczestnik);
        $this->em->flush();

        $this->addFlash('success', 'Uczestnik ' . $name . ' został usunięty.');
        return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
    }

    /**
     * Admin — import uczestników z CSV/tekstu.
     */
    #[Route('/admin/roza/{id}/import', name: 'rozaniec_admin_import', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminImport(Roza $roza, Request $request): Response
    {
        $text = '';

        // Plik ma priorytet, potem textarea
        $file = $request->files->get('import_file');
        if ($file && $file->isValid()) {
            $text = file_get_contents($file->getPathname());
        } else {
            $text = $request->request->get('import_text', '');
        }

        $text = trim($text);
        if ($text === '') {
            $this->addFlash('danger', 'Brak danych do importu — wklej tekst lub wybierz plik.');
            return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
        }

        $lines = preg_split('/\r?\n/', $text);

        // Auto-detekcja separatora na pierwszej niepustej, nie-komentarzowej linii
        $separator = ';';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $semicolons = substr_count($line, ';');
            $commas = substr_count($line, ',');
            if ($commas > $semicolons) {
                $separator = ',';
            }
            break;
        }

        // Pobierz zajęte pozycje w tej róży
        $existingUczestnicy = $this->uczestnikRepo->findByRoza($roza);
        $occupiedPositions = [];
        foreach ($existingUczestnicy as $u) {
            if ($u->getPozycja() !== null) {
                $occupiedPositions[$u->getPozycja()] = $u->getFullName();
            }
        }

        $imported = 0;
        $skipped = [];

        foreach ($lines as $lineNum => $rawLine) {
            $line = trim($rawLine);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = array_map('trim', explode($separator, $line));

            // pozycja;imię;nazwisko;email;telefon
            $pozycja = isset($parts[0]) ? (int) $parts[0] : 0;
            $firstName = $parts[1] ?? '';
            $lastName = $parts[2] ?? '';
            $email = $parts[3] ?? '';
            $telefon = $parts[4] ?? '';

            $lineLabel = 'Linia ' . ($lineNum + 1);

            if ($pozycja < 1 || $pozycja > 20) {
                $skipped[] = $lineLabel . ': nieprawidłowa pozycja (' . $pozycja . ')';
                continue;
            }

            if ($firstName === '' || $lastName === '') {
                $skipped[] = $lineLabel . ': brak imienia lub nazwiska';
                continue;
            }

            if (isset($occupiedPositions[$pozycja])) {
                $skipped[] = $lineLabel . ': pozycja ' . $pozycja . ' zajęta przez ' . $occupiedPositions[$pozycja];
                continue;
            }

            $uczestnik = new Uczestnik();
            $uczestnik->setRoza($roza);
            $uczestnik->setFirstName($firstName);
            $uczestnik->setLastName($lastName);
            $uczestnik->setPozycja($pozycja);

            if ($email !== '') {
                $uczestnik->setEmail($email);
            }
            if ($telefon !== '') {
                $uczestnik->setTelefon($telefon);
            }

            $this->em->persist($uczestnik);
            $occupiedPositions[$pozycja] = $firstName . ' ' . $lastName;
            $imported++;
        }

        $this->em->flush();

        $msg = 'Zaimportowano ' . $imported . ' uczestników.';
        if (count($skipped) > 0) {
            $msg .= ' Pominięto ' . count($skipped) . ': ' . implode('; ', $skipped) . '.';
        }

        $this->addFlash($imported > 0 ? 'success' : 'warning', $msg);

        return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
    }

    /**
     * Admin — wyślij SMS bezpośrednio przez SerwerSMS API.
     */
    #[Route('/admin/roza/{id}/uczestnik/{uczestnikId}/sms', name: 'rozaniec_admin_send_sms', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminSendSms(Roza $roza, int $uczestnikId): Response
    {
        $uczestnik = $this->uczestnikRepo->find($uczestnikId);
        if (!$uczestnik || $uczestnik->getRoza()->getId() !== $roza->getId()) {
            throw $this->createNotFoundException();
        }

        $phone = $uczestnik->getTelefon();
        if (!$phone) {
            $this->addFlash('danger', $uczestnik->getFullName() . ' — brak numeru telefonu.');
            return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
        }

        if (!$uczestnik->getPozycja()) {
            $this->addFlash('danger', $uczestnik->getFullName() . ' — brak przypisanej pozycji.');
            return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
        }

        // Znajdź tajemnicę
        $pairs = $this->rotacjaService->getUczestnicyWithTajemnice($roza);
        $pair = $pairs[$uczestnik->getPozycja()] ?? null;
        if (!$pair || !$pair['tajemnica']) {
            $this->addFlash('danger', 'Nie znaleziono tajemnicy dla pozycji ' . $uczestnik->getPozycja() . '.');
            return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
        }

        $tajemnica = $pair['tajemnica'];
        $czescNazwa = $tajemnica->getCzesc()->getNazwa();
        if (str_contains($czescNazwa, ' - ')) {
            $czescNazwa = substr($czescNazwa, strpos($czescNazwa, ' - ') + 3);
        }

        $miesiace = [
            1 => 'styczeń', 2 => 'luty', 3 => 'marzec', 4 => 'kwiecień',
            5 => 'maj', 6 => 'czerwiec', 7 => 'lipiec', 8 => 'sierpień',
            9 => 'wrzesień', 10 => 'październik', 11 => 'listopad', 12 => 'grudzień',
        ];
        $now = new \DateTimeImmutable();
        $miesiac = $miesiace[(int) $now->format('n')] . ' ' . $now->format('Y');

        $kolejnosc = $tajemnica->getKolejnosc()->getNumer();

        $text = sprintf(
            '%s (%s): %d. %s (%s). Módl się codziennie jedną dziesiątką!',
            $roza->getNazwa(),
            $miesiac,
            $kolejnosc,
            $tajemnica->getNazwa(),
            $czescNazwa,
        );

        // Token z DSN: serwersms://TOKEN@default
        $dsn = $_ENV['SERWERSMS_DSN'] ?? '';
        $token = '';
        if (preg_match('#^serwersms://([^@]+)@#', $dsn, $m)) {
            $token = $m[1];
        }

        if (!$token) {
            $this->addFlash('danger', 'Brak tokenu SerwerSMS — ustaw SERWERSMS_DSN w .env.local.');
            return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
        }

        try {
            $api = new SerwerSMS($token);
            $result = $api->messages->sendSms($phone, $text, null, ['details' => true, 'utf' => true]);

            if (!empty($result->success) && ($result->queued ?? 0) > 0) {
                $this->addFlash('success', 'SMS wysłany do ' . $uczestnik->getFullName() . ' (' . $phone . ').');
            } else {
                $error = $result->error ?? $result->message ?? json_encode($result);
                $this->addFlash('danger', 'SerwerSMS nie przyjął wiadomości: ' . (is_string($error) ? $error : json_encode($error)));
            }
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Błąd wysyłki SMS: ' . $e->getMessage());
        }

        return $this->redirectToRoute('rozaniec_admin_roza', ['id' => $roza->getId()]);
    }

    // ========== HELPERS ==========

    private function resolveFirstName(object $user): string
    {
        foreach (['getFirstName', 'getPrenom'] as $method) {
            if (method_exists($user, $method) && $user->$method()) {
                return $user->$method();
            }
        }
        // Fallback: first part of userIdentifier
        if (method_exists($user, 'getUserIdentifier')) {
            $parts = explode('@', $user->getUserIdentifier());
            return ucfirst($parts[0]);
        }
        return 'Uczestnik';
    }

    private function resolveLastName(object $user): string
    {
        foreach (['getLastName', 'getNom'] as $method) {
            if (method_exists($user, $method) && $user->$method()) {
                return $user->$method();
            }
        }
        return '';
    }
}
