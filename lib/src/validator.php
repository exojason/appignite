<?php
class Validator
{
    public static function isInteger($input)
    {
        return preg_match('/^(\+|-){0,1}\d+$/i', trim($input));
    }
    
    public static function isPositiveInteger($input)
    {
        return preg_match('/^(\+){0,1}\d+$/i', trim($input));
    }
    
    public static function isNegativeInteger($input)
    {
        return preg_match('/^(-)\d+$/i', trim($input));
    }
    
    public static function isNumber($input)
    {
        return is_numeric($input);
    }
    
    public static function isPositiveNumber($input)
    {
        return is_numeric($input) && floatval($input) >= 0;
    }
    
    public static function isNegativeNumber($input)
    {
        return is_numeric($input) && floatval($input) < 0;
    }
    
    public static function isUsername($input)
    {
        return preg_match('/^[a-zA-Z0-9_-]{3,15}$/', $input);
    }    
    
    public static function isEmail($input)
    {
        return filter_var($input, FILTER_VALIDATE_EMAIL);
    }
    
    public static function isURL($input)
    {
        return preg_match('/^(http(s)?:\/\/)?[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})(:[0-9]+)?(\/.*)?$/', $input);
    }
    
    public static function isDate($input)
    {
        // We're assuming this is a MySQL Date format YYYY-MM-DD
        if ($input && strlen($input) == 10) {
            $dateParts = explode('-', $input);

            if (count($dateParts) == 3) {
                list($year, $month, $day) = $dateParts;
                return checkdate($month, $day, $year);
            }
        }

        return false;
    }
}
?>