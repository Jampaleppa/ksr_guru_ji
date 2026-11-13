<?php
/**
 * database/payment_state_patch.php
 * ============================================================
 * HOW TO APPLY:
 *   This file documents the EXACT changes to make in
 *   payment_state.php to add the two new states needed
 *   by the KSR PAY Release 1 reconciliation flow.
 *
 *   DO NOT run this file — it is a documentation/patch file.
 *   Apply the changes manually to payment_state.php.
 * ============================================================
 *
 * NEW STATES:
 *   upi_pending_verification  — UTR submitted, admin reviewing
 *   bank_credit_imported      — matching bank credit found, not yet confirmed
 *
 * ── PATCH 1: ksrPaymentStates() ──────────────────────────────
 * Replace:
 *       'upi_awaiting',
 *   ];
 *
 * With:
 *       'upi_awaiting',
 *       'upi_pending_verification',
 *       'bank_credit_imported',
 *   ];
 *
 * ── PATCH 2: paymentStatusCanTransition() — $allowed array ───
 * Replace:
 *       'upi_awaiting' => ['processing', 'success', 'failed', 'cancelled', 'expired'],
 *
 * With:
 *       'upi_awaiting' => [
 *           'upi_pending_verification', 'processing', 'success', 'failed', 'cancelled', 'expired'
 *       ],
 *       'upi_pending_verification' => [
 *           'bank_credit_imported', 'success', 'failed', 'cancelled'
 *       ],
 *       'bank_credit_imported' => ['success', 'failed'],
 *
 * ── PATCH 3: ksrPaymentStatusMessage() ───────────────────────
 * Add these two lines before the default case:
 *       'upi_pending_verification' => 'Your UTR is being reviewed by our team.',
 *       'bank_credit_imported'     => 'Matching bank credit found. Confirming payment.',
 *
 * ── PATCH 4: ksrOrderPaymentStatusValue() ────────────────────
 * Add these two lines before the default case:
 *       'upi_pending_verification' => 'upi_awaiting',
 *       'bank_credit_imported'     => 'upi_awaiting',
 *
 * ── PATCH 5: orders.payment_status ENUM ──────────────────────
 * Run database/payment_migration.sql — it already extends the
 * orders.payment_status ENUM with all new values.
 * ============================================================
 */

