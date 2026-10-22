<?php

namespace Blog\View\Helper;

use AppHelper;
use Blog\Lib\Markdown;

class MarkdownHelper extends AppHelper
{
    /**
     * @param string $text Markdown
     * @return string
     */
    public function render($text)
    {
        return Markdown::render($text);
    }
}
