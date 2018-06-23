<?php

namespace MgGoGetSsl\Util;

class Inflector
{
    
    /**
     * @param $name
     * @return string
     */
    public static function createSlug($name)
    {
        if (empty($name)) {
            return '';
        }
        $slug = strtr($name, [
            'ą' => 'a',
            'ę' => 'e',
            'ś' => 's',
            'ć' => 'c',
            'ł' => 'l',
            'ó' => 'o',
            'ź' => 'z',
            'ż' => 'z',
            'ń' => 'n',
            'Ą' => 'a',
            'Ę' => 'e',
            'Ś' => 's',
            'Ć' => 'c',
            'Ł' => 'l',
            'Ó' => 'o',
            'Ź' => 'z',
            'Ż' => 'z',
            'Ń' => 'n',
        ]);
        $replaced = str_replace([' ', ',', '.', '*', '&', '!', '^', '%', '$', '#', '@', '(', ')', '\'', '"', '[', ']', ';', '?', '~', '`', ':', '+', '='], '-', $slug);
        $replaced = str_replace(['---', '--', ' ', '-'], '-', $replaced);
        if (in_array($replaced[strlen($replaced) - 1], ['-', '.', ',', ' '])) {
            $replaced = substr($replaced, 0, strlen($replaced) - 1);
        }
        
        return strtolower($replaced);
    }
    
    /**
     * @param int  $length
     * @param bool $onlyLetters
     * @return bool|string
     */
    public static function randomString($length = 8, $onlyLetters = false)
    {
        $alphaNumeric = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz' . ($onlyLetters ? '' : '0123456789');
        return substr(str_shuffle($alphaNumeric), 0, $length);
    }
    
    /**
     * $mode = 1 - add random prefix (before) fileName, 2 - add random postfix (after) fileName
     *
     * @param string $name
     * @param int    $maxLength
     * @param int    $mode
     * @param int    $randomLength
     * @param string $separator
     * @return string;
     */
    public static function normalizeFileName(
        $name, $maxLength = 20, $mode = 2, $randomLength = 3, $separator = '_')
    {
        $slug = self::createSlug($name);
        
        if (strlen($slug) > $maxLength) {
            $tmp = substr($slug, 0, 20);
        } else {
            $tmp = $slug;
        }
        
        $random = self::randomString($randomLength);
        
        if ($mode == 1) {
            $fileName = $random . $separator . $tmp;
        } else if ($mode == 2) {
            $fileName = $tmp . $separator . $random;
        }
        
        return $fileName;
    }
    
    /**
     * @param string $string
     * @param bool   $capitalizeFirstCharacter
     * @return mixed|string
     */
    public static function toCamelCase($string, $capitalizeFirstCharacter = false)
    {
        if (empty($string)) {
            return $string;
        }
        $str = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $string)));
        if (!$capitalizeFirstCharacter) {
            $str[0] = strtolower($str[0]);
        }
        
        return $str;
    }
    
    /**
     * @param string $input
     * @param bool   $capitalizeFirstCharacter
     * @return string
     */
    public static function to_underscore($input, $capitalizeFirstCharacter = false)
    {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
        $ret = $matches[0];

        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }
        
        $result = implode('_', $ret);
        if ($capitalizeFirstCharacter) {
            return ucfirst($result);
        }
        
        return $result;
    }

    /**
     * @param string $string
     * @param string $char
     * @return string
     */
    public static function trimLastChar($string, $char)
    {
        return substr($string, -1) == $char ? substr($string, 0, -1) : $string;
    }

    /**
     * @param string $string
     * @param string $char
     * @return string
     */
    public static function trimFirstChar($string, $char)
    {
        return substr($string, 0, 1) == $char ? substr($string, 1) : $string;
    }

    /**
     * @param string $content
     * @return array
     */
    public static function explodeByNewLine($content)
    {
        return preg_split('/\r\n|\r|\n/', $content);
    }

    /**
     * @param strning $domain
     * @return bool
     */
    public static function validateDomain($domain)
    {
        return (bool) preg_match("/^([-a-z0-9]{2,100})\.([a-z\.]{2,8})$/i", $domain);
    }

    /**
     * @param string $domain
     * @return bool
     */
    public static function validateDomainOld($domain)
    {
        return preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domain)
            && preg_match("/^.{1,253}$/", $domain)
            && preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domain)
            && preg_match('/[^.\d]/', $domain);
    }

}
