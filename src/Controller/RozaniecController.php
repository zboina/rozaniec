<?php

namespace Rozaniec\RozaniecBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Rozaniec\RozaniecBundle\Entity\Tajemnica;
use Rozaniec\RozaniecBundle\Model\RozaniecUserInterface;
use Rozaniec\RozaniecBundle\Service\RotacjaService;
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
    ) {
    }

    #[Route('/', name: 'rozaniec_index')]
    public function index(): Response
    {
        $this->rotacjaService->ensureRotated();
        $tajemnice = $this->rotacjaService->getTajemnice();

        // Grupuj po częściach
        $grouped = [];
        foreach ($tajemnice as $t) {
            $czescNazwa = $t->getCzesc()->getNazwa();
            $grouped[$czescNazwa][] = $t;
        }

        return $this->render('@Rozaniec/index.html.twig', [
            'grouped' => $grouped,
            'tajemnice' => $tajemnice,
        ]);
    }

    #[Route('/toggle/{id}', name: 'rozaniec_toggle', methods: ['POST'])]
    public function toggle(Tajemnica $tajemnica, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('rozaniec_toggle_' . $tajemnica->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Nieprawidłowy token CSRF.');
            return $this->redirectToRoute('rozaniec_index');
        }

        /** @var RozaniecUserInterface $currentUser */
        $currentUser = $this->getUser();

        if ($tajemnica->getUser() !== null && $tajemnica->getUser()->getId() === $currentUser->getId()) {
            // Odpisanie — user rezygnuje
            $tajemnica->setUser(null);
        } elseif ($tajemnica->getUser() === null) {
            // Przypisanie — user się zapisuje
            $tajemnica->setUser($currentUser);
        } else {
            // Tajemnica zajęta przez innego usera
            $this->addFlash('danger', 'Ta tajemnica jest już przypisana do innej osoby.');
            return $this->redirectToRoute('rozaniec_index');
        }

        $this->em->flush();

        return $this->redirectToRoute('rozaniec_index');
    }

    #[Route('/assign/{id}', name: 'rozaniec_assign', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function assign(Tajemnica $tajemnica, Request $request): Response
    {
        $userId = $request->request->get('user_id');

        if ($userId) {
            $userClass = $this->getParameter('rozaniec.user_class');
            $user = $this->em->getRepository($userClass)->find($userId);
            $tajemnica->setUser($user);
        } else {
            $tajemnica->setUser(null);
        }

        $this->em->flush();

        return $this->redirectToRoute('rozaniec_index');
    }
}
