<?php
App::uses('AppController', 'Controller');

class ProductsController extends AppController
{
    public $uses = ['Product'];

    public function index()
    {
        $this->set('products', $this->Product->find('all'));
    }
}
