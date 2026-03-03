<?php

namespace Rozaniec\RozaniecBundle\Service;

use Rozaniec\RozaniecBundle\Entity\Roza;
use Rozaniec\RozaniecBundle\Entity\Uczestnik;
use Rozaniec\RozaniecBundle\Notification\RotacjaNotification;
use Rozaniec\RozaniecBundle\Repository\RozaniecConfigRepository;
use SerwerSMS\SerwerSMS;
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
        private string $emailFrom = 'rozaniec@localhost',
        private ?LoggerInterface $logger = null,
    ) {
    }

    private function getSmsToken(): ?string
    {
        $dsn = $_ENV['SERWERSMS_DSN'] ?? '';
        if (preg_match('#^serwersms://([^@]+)@#', $dsn, $m)) {
            return $m[1];
        }
        return null;
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

            $this->doNotifyUczestnik($uczestnik, $tajemnica->getNazwa(), $tajemnica->getCzesc()->getNazwa(), $tajemnica->getKolejnosc()->getNumer(), $globalChannels, $miesiac, $roza->getNazwa());
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
        $kolejnosc = $tajemnica->getKolejnosc()->getNumer();
        $miesiac = self::polskiMiesiac(new \DateTimeImmutable());

        $globalChannels = $this->getGlobalEnabledChannels();
        if (empty($globalChannels)) {
            $globalChannels = ['email'];
        }

        $this->doNotifyUczestnik($uczestnik, $tajemnica->getNazwa(), $czescNazwa, $kolejnosc, $globalChannels, $miesiac, $roza->getNazwa());
        return true;
    }

    private function doNotifyUczestnik(Uczestnik $uczestnik, string $tajemnicaNazwa, string $czescNazwa, int $kolejnosc, array $globalChannels, string $miesiac, string $rozaNazwa = 'Żywy Różaniec'): void
    {
        $effectiveChannels = array_intersect($globalChannels, $uczestnik->getEffectiveChannels());

        if (empty($effectiveChannels)) {
            return;
        }

        // Skróć nazwę części
        if (str_contains($czescNazwa, ' - ')) {
            $czescNazwa = substr($czescNazwa, strpos($czescNazwa, ' - ') + 3);
        }

        // Email — przez Symfony Notifier
        if (in_array('email', $effectiveChannels) && $uczestnik->getEmail()) {
            $notification = new RotacjaNotification($tajemnicaNazwa, $czescNazwa, $uczestnik->getFullName(), $miesiac, $rozaNazwa, $kolejnosc, $this->emailFrom);
            $recipient = new Recipient($uczestnik->getEmail(), '');
            try {
                $this->notifier->send($notification, $recipient);
            } catch (\Throwable $e) {
                $this->logger?->error('Rozaniec: błąd email do #{id}: {error}', [
                    'id' => $uczestnik->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // SMS — bezpośrednio przez SerwerSMS
        if (in_array('sms', $effectiveChannels) && $uczestnik->getTelefon()) {
            $token = $this->getSmsToken();
            if (!$token) {
                $this->logger?->warning('Rozaniec: brak tokenu SERWERSMS_DSN, SMS nie wysłany do #{id}', [
                    'id' => $uczestnik->getId(),
                ]);
                return;
            }

            $text = sprintf(
                '%s (%s): %d. %s (%s). Módl się codziennie jedną dziesiątką!',
                $rozaNazwa, $miesiac, $kolejnosc, $tajemnicaNazwa, $czescNazwa,
            );

            try {
                $api = new SerwerSMS($token);
                $result = $api->messages->sendSms($uczestnik->getTelefon(), $text, null, ['details' => true, 'utf' => true]);

                if (empty($result->success)) {
                    $error = $result->error ?? $result->message ?? json_encode($result);
                    $this->logger?->error('Rozaniec: SMS do #{id} odrzucony: {error}', [
                        'id' => $uczestnik->getId(),
                        'error' => is_string($error) ? $error : json_encode($error),
                    ]);
                }
            } catch (\Throwable $e) {
                $this->logger?->error('Rozaniec: błąd SMS do #{id}: {error}', [
                    'id' => $uczestnik->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
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

    /**
     * Wysyła SMS-przypomnienie do zelatora róży o jutrzejszej wymianie tajemnic.
     * Zwraca true jeśli SMS został wysłany.
     */
    public function notifyZelatorReminder(Roza $roza): bool
    {
        $zelator = $roza->getZelator();
        if (!$zelator || !$zelator->getTelefon()) {
            return false;
        }

        $token = $this->getSmsToken();
        if (!$token) {
            $this->logger?->warning('Rozaniec: brak tokenu SERWERSMS_DSN, SMS przypomnienia zelatora nie wysłany dla róży #{id}', [
                'id' => $roza->getId(),
            ]);
            return false;
        }

        $text = sprintf(
            '%s: Przypomnienie — jutro (niedziela) wymiana tajemnic różańcowych.',
            $roza->getNazwa(),
        );

        try {
            $api = new SerwerSMS($token);
            $result = $api->messages->sendSms($zelator->getTelefon(), $text, null, ['details' => true, 'utf' => true]);

            if (empty($result->success)) {
                $error = $result->error ?? $result->message ?? json_encode($result);
                $this->logger?->error('Rozaniec: SMS przypomnienia zelatora dla róży #{rozaId} odrzucony: {error}', [
                    'rozaId' => $roza->getId(),
                    'error' => is_string($error) ? $error : json_encode($error),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger?->error('Rozaniec: błąd SMS przypomnienia zelatora dla róży #{rozaId}: {error}', [
                'rozaId' => $roza->getId(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
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
