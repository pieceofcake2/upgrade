<?php

namespace Blog\Lib;

class Markdown
{
    /**
     * @param string $text Markdown
     * @return string
     */
    public static function render($text)
    {
        return h($text);
    }
}
