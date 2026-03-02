<?php

namespace Rozaniec\RozaniecBundle\Service;

use Rozaniec\RozaniecBundle\Entity\Roza;
use Rozaniec\RozaniecBundle\Entity\Uczestnik;
use Rozaniec\RozaniecBundle\Notification\RotacjaNotification;
use Rozaniec\RozaniecBundle\Repository\RozaniecConfigRepository;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Psr\Log\LoggerInterface;

class RozaniecNotifier
{
    /** Dostępne kanały powiadomień */
    public const CHANNELS = ['email', 'sms'];

    public function __construct(
        private NotifierInterface $notifier,
        private RozaniecConfigRepository $configRepo,
        private RotacjaService $rotacjaService,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Wysyła powiadomienia o nowej tajemnicy do uczestników danej róży.
     */
    public function notifyAllAfterRotation(Roza $roza): void
    {
        if ($this->configRepo->get('notify_enabled') === '0') {
            return;
        }

        $globalChannels = $this->getGlobalEnabledChannels();
        if (empty($globalChannels)) {
            return;
        }

        $pairs = $this->rotacjaService->getUczestnicyWithTajemnice($roza);
        $miesiac = self::polskiMiesiac(new \DateTimeImmutable());

        foreach ($pairs as $pair) {
            $uczestnik = $pair['uczestnik'];
            $tajemnica = $pair['tajemnica'];

            if (!$uczestnik) {
                continue;
            }

            $this->doNotifyUczestnik($uczestnik, $tajemnica->getNazwa(), $tajemnica->getCzesc()->getNazwa(), $globalChannels, $miesiac, $roza->getNazwa());
        }
    }

    /**
     * Wysyła powiadomienie o aktualnej tajemnicy do jednego uczestnika.
     * Zwraca true jeśli wysłano, false jeśli brak kanałów/danych.
     */
    public function notifySingle(Uczestnik $uczestnik, Roza $roza): bool
    {
        if (!$uczestnik->getPozycja()) {
            return false;
        }

        $pairs = $this->rotacjaService->getUczestnicyWithTajemnice($roza);
        $pair = $pairs[$uczestnik->getPozycja()] ?? null;
        if (!$pair || !$pair['tajemnica']) {
            return false;
        }

        $tajemnica = $pair['tajemnica'];
        $czescNazwa = $tajemnica->getCzesc()->getNazwa();
        $miesiac = self::polskiMiesiac(new \DateTimeImmutable());

        $globalChannels = $this->getGlobalEnabledChannels();
        if (empty($globalChannels)) {
            $globalChannels = ['email'];
        }

        $this->doNotifyUczestnik($uczestnik, $tajemnica->getNazwa(), $czescNazwa, $globalChannels, $miesiac, $roza->getNazwa());
        return true;
    }

    private function doNotifyUczestnik(Uczestnik $uczestnik, string $tajemnicaNazwa, string $czescNazwa, array $globalChannels, string $miesiac, string $rozaNazwa = 'Żywy Różaniec'): void
    {
        // Kanały efektywne = przecięcie globalnych + tych co uczestnik MA (dane + preferencje)
        $effectiveChannels = array_intersect($globalChannels, $uczestnik->getEffectiveChannels());

        if (empty($effectiveChannels)) {
            return;
        }

        $userName = $uczestnik->getFullName();

        $email = in_array('email', $effectiveChannels) ? $uczestnik->getEmail() : null;
        $phone = in_array('sms', $effectiveChannels) ? $uczestnik->getTelefon() : null;

        if (!$email && !$phone) {
            return;
        }

        // Skróć nazwę części
        if (str_contains($czescNazwa, ' - ')) {
            $czescNazwa = substr($czescNazwa, strpos($czescNazwa, ' - ') + 3);
        }

        $notification = new RotacjaNotification(
            $tajemnicaNazwa,
            $czescNazwa,
            $userName,
            $miesiac,
            $rozaNazwa,
        );

        $recipient = new Recipient($email ?? '', $phone ?? '');

        try {
            $this->notifier->send($notification, $recipient);
        } catch (\Throwable $e) {
            $this->logger?->error('Rozaniec: błąd wysyłki powiadomienia do uczestnika #{id}: {error}', [
                'id' => $uczestnik->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return string[]
     */
    public function getGlobalEnabledChannels(): array
    {
        $channels = [];
        foreach (self::CHANNELS as $channel) {
            $key = 'notify_' . $channel . '_enabled';
            if ($this->configRepo->get($key) !== '0') {
                $channels[] = $channel;
            }
        }
        return $channels;
    }

    public function isEnabled(): bool
    {
        return $this->configRepo->get('notify_enabled') !== '0';
    }

    private static function polskiMiesiac(\DateTimeInterface $date): string
    {
        $miesiace = [
            1 => 'styczeń', 2 => 'luty', 3 => 'marzec', 4 => 'kwiecień',
            5 => 'maj', 6 => 'czerwiec', 7 => 'lipiec', 8 => 'sierpień',
            9 => 'wrzesień', 10 => 'październik', 11 => 'listopad', 12 => 'grudzień',
        ];

        return $miesiace[(int) $date->format('n')] . ' ' . $date->format('Y');
    }
}
