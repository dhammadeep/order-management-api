<?php

declare(strict_types=1);

namespace App\Http\Payment\Exceptions;

use RuntimeException;

/**
 * Raised when a verified transaction conflicts with payment or order state.
 *
 * @author Dhammadeep
 */
final class PaymentConflictException extends RuntimeException {}
