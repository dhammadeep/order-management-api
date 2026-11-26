<?php

declare(strict_types=1);

namespace App\Mail;

use App\Http\Order\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued confirmation containing the saved order item snapshots.
 *
 * @author Dhammadeep
 */
final class OrderConfirmationMail extends Mailable implements ShouldQueueAfterCommit
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30];

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Order confirmation '.$this->order->order_number,
        );
    }

    public function content(): Content
    {
        $this->order->loadMissing('items');

        return new Content(
            view: 'emails.order-confirmation',
            with: ['order' => $this->order],
        );
    }

    /**
     * Log only non-personal identifiers when Laravel marks a job permanently failed.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Order confirmation email failed after retries.', [
            'order_id' => $this->order->getKey(),
            'exception_type' => $exception::class,
        ]);
    }
}
