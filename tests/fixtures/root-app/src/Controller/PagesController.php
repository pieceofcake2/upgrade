<?php
App::uses('AppController', 'Controller');

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
