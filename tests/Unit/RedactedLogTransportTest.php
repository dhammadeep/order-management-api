<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Mail\Transports\RedactedLogTransport;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

/**
 * Ensures local mail logging does not include recipient or message data.
 *
 * @author Dhammadeep
 */
final class RedactedLogTransportTest extends TestCase
{
    public function test_transport_logs_only_a_generic_processing_message(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('Order confirmation handled by the local redacted mail transport.');

        $transport = new RedactedLogTransport($logger);
        $sentMessage = $transport->send(
            new RawMessage("To: private@example.test\r\n\r\nprivate message body"),
            new Envelope(new Address('sender@example.test'), [new Address('private@example.test')]),
        );

        self::assertInstanceOf(SentMessage::class, $sentMessage);
        self::assertSame('redacted-log', (string) $transport);
    }
}
