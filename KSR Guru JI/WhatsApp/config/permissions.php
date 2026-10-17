<?php

return [
    'roles' => [
        'USER' => ['login', 'chat', 'shop', 'order'],
        'PATIENT' => ['login', 'chat', 'book_appointment', 'view_prescriptions'],
        'DOCTOR' => ['login', 'chat', 'manage_appointments', 'write_prescriptions'],
        'PHARMACIST' => ['login', 'chat', 'verify_prescriptions', 'fulfill_orders'],
        'SELLER' => ['login', 'manage_products', 'manage_orders'],
        'DELIVERY_PARTNER' => ['login', 'track_deliveries', 'update_status'],
        'SUPPORT' => ['login', 'manage_tickets', 'chat_with_users'],
        'ADMIN' => ['login', 'dashboard_access', 'manage_users', 'audit_logs'],
        'SUPER_ADMIN' => ['*'] // Has access to all system privileges
    ],
    
    // Core routes restrictions
    'restricted_routes' => [
        '/api/doctors' => ['DOCTOR', 'ADMIN', 'SUPER_ADMIN'],
        '/api/admin' => ['ADMIN', 'SUPER_ADMIN'],
        '/api/pharmacy' => ['PHARMACIST', 'ADMIN', 'SUPER_ADMIN']
    ]
];
