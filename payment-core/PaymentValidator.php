<?php

declare(strict_types=1);

namespace KsrPay;

/**
 * PaymentValidator — Input validation for payment parameters.
 *
 * All methods throw \InvalidArgumentException on failure.
 */
class PaymentValidator
{
    private const ALLOWED_METHODS = ['upi', 'cod', 'bank_transfer', 'card'];

    /**
     * Validate that the submitted amount matches the order amount exactly (paise).
     */
    public function validateAmount(int $orderAmountPaise, int $submittedAmountPaise): bool
    {
        if ($orderAmountPaise !== $submittedAmountPaise) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Amount mismatch: order requires %d paise but %d paise was submitted.',
                    $orderAmountPaise,
                    $submittedAmountPaise
                )
            );
        }
        return true;
    }

    /**
     * Validate that the payment method is one of the allowed values.
     */
    public function validateMethod(string $method): bool
    {
        if (!in_array($method, self::ALLOWED_METHODS, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid payment method "%s". Allowed: %s.',
                    $method,
                    implode(', ', self::ALLOWED_METHODS)
                )
            );
        }
        return true;
    }

    /**
     * Validate a UTR (Unique Transaction Reference): 10–22 alphanumeric characters.
     */
    public function validateUTR(string $utr): bool
    {
        if (!preg_match('/^[A-Za-z0-9]{10,22}$/', $utr)) {
            throw new \InvalidArgumentException(
                'Invalid UTR: must be 10–22 alphanumeric characters.'
            );
        }
        return true;
    }

    /**
     * Validate that the currency is INR.
     */
    public function validateCurrency(string $currency): bool
    {
        if ($currency !== 'INR') {
            throw new \InvalidArgumentException(
                sprintf('Invalid currency "%s". Only INR is supported.', $currency)
            );
        }
        return true;
    }

    /**
     * Validate an idempotency key: non-empty, max 100 chars.
     */
    public function validateIdempotencyKey(string $key): bool
    {
        if ($key === '') {
            throw new \InvalidArgumentException('Idempotency key must not be empty.');
        }
        if (mb_strlen($key) > 100) {
            throw new \InvalidArgumentException(
                'Idempotency key must not exceed 100 characters.'
            );
        }
        return true;
    }
}
