<?php
namespace RazCrypto\Support;

/**
 * Small helper to safely get nested values.
 */
class Arr
{
    public static function get(array $array, string $key, $default = null)
    {
        if (array_key_exists($key, $array)) return $array[$key];
        return $default;
    }
}
