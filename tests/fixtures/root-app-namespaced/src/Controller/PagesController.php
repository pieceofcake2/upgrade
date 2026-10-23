<?php

namespace App\Controller;

/**
 * Static pages
 */
class PagesController extends AppController
{
    public $uses = [];

    public function display(...$path)
    {
        $this->render(implode('/', $path));
    }
}
