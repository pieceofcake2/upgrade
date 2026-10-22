<?php
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
