<?php

namespace App\Controller;

use Cake\Error\NotFoundException;
use App\Model\Post;
use Cake\Controller\Component\PaginatorComponent;
use App\Controller\Component\GreetingComponent;
use App\Utility\Slug;
use DateTime;
use Cake\View\XmlView;
use Cake\Controller\Component\RequestHandlerComponent;
use App\Lib\MyTool;
use Cake\Utility\ClassRegistry;
use App\Console\Command\ImportShell;
use App\Model\AppModel;
use ReflectionClass;

class PostsController extends AppController
{
    public $uses = [Post::class, 'Blog.Comment'];

    public $components = [PaginatorComponent::class, 'Mailer' => ['className' => GreetingComponent::class]];

    /**
     * @param string $slug Slug
     * @return void
     * @throws NotFoundException
     */
    public function view($slug)
    {
        $post = $this->Post->findBySlug(Slug::normalize($slug));
        if (!$post) {
            throw new NotFoundException();
        }
        if ($this->Post instanceof Post) {
            $this->set('plain', true);
        }
        $this->set('date', new DateTime());
        $this->set(compact('post'));
    }

    public function feed()
    {
        $this->viewClass = XmlView::class;
        $this->Components->load(RequestHandlerComponent::class);
        $tool = new MyTool();
        $this->set('tool', $tool);
    }

    public function stats()
    {
        $model = ClassRegistry::init(Post::class);
        if (class_exists(ImportShell::class) && is_a($model, AppModel::class)) {
            $this->set('count', $model->find('count'));
        }
        $slugs = array_map([Slug::class, 'normalize'], ['A title']);
        $first = call_user_func([Slug::class, 'normalize'], 'B title');
        $methods = (new ReflectionClass(MyTool::class))->getMethods();
        $this->set(compact('slugs', 'first', 'methods'));
    }
}
