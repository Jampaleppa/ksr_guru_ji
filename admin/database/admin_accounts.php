<?php
// ============================================================
// admin/database/admin_accounts.php
// ------------------------------------------------------------
// Single source of truth for the production admin identities.
//
// The username "KSR GURU JI" is UNIQUE in `admin_users`, so it can
// belong to exactly ONE account. We give it to the SUPER ADMIN (the
// owner), because the Super Admin is the account that can always
// recover the system (manage admins, roles and permissions).
//
// The plain ADMIN signs in with the same username/email but is
// resolved by the email address.
//
//   Super Admin : username/email "KSR GURU JI"  -> WhatsApp OTP
//   Admin       : email "ksrguruji.admin@ksrguruji.in"  (no OTP)
//
// !! Keep this file OUT of the public web root / out of git. !!
// ============================================================

return [

    // ------------------------------------------------------------
    // Super Admin - full access, WhatsApp OTP second factor
    // ------------------------------------------------------------
    [
        'username'        => 'KSR GURU JI',
        'email'           => 'ksrguruji.owner@ksrguruji.in',
        'password'        => 'KSRParvatiGauri',
        'full_name'       => 'KSR GURU JI - Owner',
        'phone'           => '918088584421',   // WhatsApp OTP: 8088584421
        'role_key'        => 'SUPER_ADMIN',
        'is_super_admin'  => 1,
        'status'          => 'active',
    ],

    // ------------------------------------------------------------
    // Admin - permission based, no OTP
    // ------------------------------------------------------------
    [
        'username'        => 'ksr-guru-ji-admin',
        'email'           => 'KSR GURU JI',
        'password'        => 'JKRathod@KSRGURUJI',
        'full_name'       => 'KSR GURU JI - Admin',
        'phone'           => null,
        'role_key'        => 'ADMIN',
        'is_super_admin'  => 0,
        'status'          => 'active',
    ],
];
