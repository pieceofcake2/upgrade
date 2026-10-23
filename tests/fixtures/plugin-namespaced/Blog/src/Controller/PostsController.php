<?php

namespace Blog\Controller;

use Blog\Controller\Component\FeedComponent;
use Blog\Lib\Markdown;
use Blog\Model\Post;
use Blog\View\Helper\MarkdownHelper;
use Cake\Controller\Component\PaginatorComponent;
use Cake\View\Helper\HtmlHelper;

class PostsController extends BlogAppController
{
    public $uses = [Post::class];

    public $components = [PaginatorComponent::class, FeedComponent::class => ['limit' => 10]];

    public $helpers = [HtmlHelper::class, MarkdownHelper::class];

    public function index()
    {
        $this->set('posts', $this->Paginator->paginate('Post'));
        $this->set('html', Markdown::render('*Blog*'));
    }
}
