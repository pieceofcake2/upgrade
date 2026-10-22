<?php
App::uses('AppController', 'Controller');
App::uses('Slug', 'Utility');

class PostsController extends AppController
{
    public $uses = ['Post', 'Blog.Comment'];

    public $components = ['Paginator', 'Mailer' => ['className' => 'Greeting']];

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
        if (get_class($this->Post) === 'Post') {
            $this->set('plain', true);
        }
        $this->set('date', new DateTime());
        $this->set(compact('post'));
    }

    public function feed()
    {
        $this->viewClass = 'Xml';
        $this->Components->load('RequestHandler');
        $tool = new MyTool();
        $this->set('tool', $tool);
    }

    public function stats()
    {
        $model = ClassRegistry::init('Post');
        if (class_exists('ImportShell') && is_a($model, 'AppModel')) {
            $this->set('count', $model->find('count'));
        }
        $slugs = array_map('Slug::normalize', ['A title']);
        $first = call_user_func(['Slug', 'normalize'], 'B title');
        $methods = (new ReflectionClass('MyTool'))->getMethods();
        $this->set(compact('slugs', 'first', 'methods'));
    }
}
