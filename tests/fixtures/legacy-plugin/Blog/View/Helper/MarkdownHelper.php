<?php
App::uses('AppHelper', 'View/Helper');
App::uses('Markdown', 'Blog.Lib');

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
