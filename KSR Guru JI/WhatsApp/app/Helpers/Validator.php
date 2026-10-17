<?php

namespace App\Helpers;

class Validator {
    public static function sanitize($input) {
        return htmlspecialchars(strip_tags(trim($input)));
    }
    
    public static function email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
