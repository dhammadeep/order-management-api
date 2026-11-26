<?php

declare(strict_types=1);

namespace App\Mail\Transports;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Local mail transport that records processing without writing recipients or message content.
 *
 * @author Dhammadeep
 */
final class RedactedLogTransport implements TransportInterface
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $this->logger->info('Order confirmation handled by the local redacted mail transport.');

        return new SentMessage($message, $envelope ?? Envelope::create($message));
    }

    public function __toString(): string
    {
        return 'redacted-log';
    }
}
