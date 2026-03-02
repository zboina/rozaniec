<?php

namespace Rozaniec\RozaniecBundle\Sms;

use SerwerSMS\SerwerSMS;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SerwerSmsTransport extends AbstractTransport
{
    protected const HOST = 'api2.serwersms.pl';

    public function __construct(
        private readonly string $accessToken,
        private readonly ?string $sender = null,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);

        $this->host = self::HOST;
    }

    public function __toString(): string
    {
        return sprintf('serwersms://%s', $this->getEndpoint());
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $api = new SerwerSMS($this->accessToken);

        try {
            $result = $api->messages->sendSms(
                $message->getPhone(),
                $message->getSubject(),
                $this->sender,
                ['details' => true, 'utf' => true],
            );
        } catch (\Exception $e) {
            throw new TransportException(
                sprintf('Unable to send SMS via SerwerSMS: %s', $e->getMessage()),
            );
        }

        if (empty($result->success)) {
            $error = $result->error ?? $result->message ?? 'Unknown error';
            throw new TransportException(
                sprintf('Unable to send SMS via SerwerSMS: %s', is_string($error) ? $error : json_encode($error)),
            );
        }

        $sentMessage = new SentMessage($message, (string) $this);

        if (!empty($result->items[0]->id)) {
            $sentMessage->setMessageId($result->items[0]->id);
        }

        return $sentMessage;
    }
}
