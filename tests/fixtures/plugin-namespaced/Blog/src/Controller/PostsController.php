<?php

namespace Blog\Controller;

use Blog\Model\Post;
use Cake\Controller\Component\PaginatorComponent;
use Blog\Controller\Component\FeedComponent;
use Cake\View\Helper\HtmlHelper;
use Blog\View\Helper\MarkdownHelper;
use Blog\Lib\Markdown;

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
