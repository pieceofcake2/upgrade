<?php
App::uses('BlogAppController', 'Blog.Controller');

class PostsController extends BlogAppController
{
    public $uses = ['Blog.Post'];

    public $components = ['Paginator', 'Blog.Feed' => ['limit' => 10]];

    public $helpers = ['Html', 'Blog.Markdown'];

    public function index()
    {
        $this->set('posts', $this->Paginator->paginate('Post'));
        $this->set('html', Markdown::render('*Blog*'));
    }
}
