<?php

namespace Rozaniec\RozaniecBundle\Notification;

use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notification\EmailNotificationInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notification\SmsNotificationInterface;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\Notifier\Recipient\SmsRecipientInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class RotacjaNotification extends Notification implements EmailNotificationInterface, SmsNotificationInterface
{
    public function __construct(
        private string $tajemnicaNazwa,
        private string $czescNazwa,
        private string $userName,
        private string $miesiac,
        private string $rozaNazwa = 'Żywy Różaniec',
        private int $kolejnosc = 0,
        private string $emailFrom = 'rozaniec@localhost',
    ) {
        parent::__construct($this->rozaNazwa . ' — nowa tajemnica');
        $this->importance(Notification::IMPORTANCE_HIGH);
    }

    public function asEmailMessage(EmailRecipientInterface $recipient, ?string $transport = null): ?EmailMessage
    {
        $email = (new TemplatedEmail())
            ->from($this->emailFrom)
            ->to($recipient->getEmail())
            ->subject($this->rozaNazwa . ' — Twoja tajemnica na ' . $this->miesiac)
            ->htmlTemplate('@Rozaniec/notification/rotacja_email.html.twig')
            ->context([
                'userName' => $this->userName,
                'tajemnicaNazwa' => $this->tajemnicaNazwa,
                'czescNazwa' => $this->czescNazwa,
                'miesiac' => $this->miesiac,
                'rozaNazwa' => $this->rozaNazwa,
                'kolejnosc' => $this->kolejnosc,
            ]);

        return new EmailMessage($email);
    }

    public function asSmsMessage(SmsRecipientInterface $recipient, ?string $transport = null): ?SmsMessage
    {
        $text = sprintf(
            '%s (%s): Twoja tajemnica — %s (%s). Módl się codziennie jedną dziesiątką!',
            $this->rozaNazwa,
            $this->miesiac,
            $this->tajemnicaNazwa,
            $this->czescNazwa,
        );

        return new SmsMessage($recipient->getPhone(), $text);
    }

    public function getChannels(/* RecipientInterface */ $recipient): array
    {
        // Kanały są kontrolowane przez RozaniecNotifier — tu zwracamy wszystkie możliwe
        $channels = [];

        if ($recipient instanceof EmailRecipientInterface && $recipient->getEmail()) {
            $channels[] = 'email';
        }

        if ($recipient instanceof SmsRecipientInterface && $recipient->getPhone()) {
            $channels[] = 'sms';
        }

        return $channels;
    }
}
