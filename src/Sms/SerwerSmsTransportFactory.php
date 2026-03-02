<?php

namespace Rozaniec\RozaniecBundle\Sms;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

final class SerwerSmsTransportFactory extends AbstractTransportFactory
{
    protected function getSupportedSchemes(): array
    {
        return ['serwersms'];
    }

    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('serwersms' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'serwersms', $this->getSupportedSchemes());
        }

        $accessToken = $this->getUser($dsn);
        $sender = $dsn->getOption('sender');

        $transport = new SerwerSmsTransport($accessToken, $sender, $this->client, $this->dispatcher);

        $host = $dsn->getHost();
        if ('default' !== $host) {
            $transport->setHost($host);
        }

        $port = $dsn->getPort();
        if (null !== $port) {
            $transport->setPort($port);
        }

        return $transport;
    }
}
