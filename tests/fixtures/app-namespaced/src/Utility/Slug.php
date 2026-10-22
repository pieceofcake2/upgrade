<?php

namespace App\Utility;

use Cake\Utility\Inflector;

class Slug
{
    /**
     * @param string $text Text
     * @return string
     */
    public static function normalize($text)
    {
        return strtolower(Inflector::slug($text, '-'));
    }
}
