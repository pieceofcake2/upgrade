<?php

namespace App\Controller;

use App\Model\Product;

class ProductsController extends AppController
{
    public $uses = [Product::class];

    public function index()
    {
        $this->set('products', $this->Product->find('all'));
    }
}
