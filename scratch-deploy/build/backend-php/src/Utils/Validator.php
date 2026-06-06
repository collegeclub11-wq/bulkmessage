<?php
namespace Utils;

class Validator {
    public static function email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function phone($phone) {
        return preg_match('/^[0-9]{8,15}$/', $phone);
    }
}
?>
