<?php

namespace App\Helpers;

class Security {
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }
    
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    public static function generateOTP() {
        return rand(100000, 999999);
    }
}
