<?php

namespace MgGoGetSsl\Util;

final class ArrayUtil
{

    /**
     * @param mixed $array
     * @return array
     */
    public static function wrapArray($array)
    {
        return is_array($array) ? $array : [$array];
    }

    /**
     * @param array $array
     * @return bool
     */
    public static function isMultiArray(array $array)
    {
        return (bool) (count($array) != count($array, COUNT_RECURSIVE));
    }

    /**
     * @param object $object
     * @return array
     */
    public static function arrayCopy($object)
    {
        $callback = [$object, 'getArrayCopy'];
        $array = is_callable($callback) ? call_user_func($callback) : get_object_vars($object);

        foreach ($array as $field => $value) {
            if (is_object($value) || is_array($value)) {
                $array[$field] = self::arrayCopy($value);
            }
        }

        return $array;
    }

}
