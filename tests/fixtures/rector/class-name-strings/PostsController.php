<?php
namespace App\Controller;

use Cake\Network\Email\CakeEmail;
use Cake\Utility\ClassRegistry;

class PostsController extends AppController
{
    public $uses = ['Post', 'Blog.Comment'];

    public $components = [
        'Session',
        'Greeting',
        'Mailer' => ['className' => 'Greeting'],
        'DebugKit.Toolbar',
        'RequestHandler' => ['viewClassMap' => ['json' => 'Json', 'csv' => 'CsvView.Csv']],
        'Auth' => [
            'authenticate' => [
                'all' => ['passwordHasher' => 'Blowfish'],
                'Form',
                'Basic' => ['passwordHasher' => ['className' => 'Simple', 'hashType' => 'sha256']],
            ],
            'authorize' => 'Controller',
        ],
    ];

    public $helpers = ['Html', 'Text' => ['engine' => 'CakeText'], 'Js' => ['Jquery']];

    public $viewClass = 'Json';

    public function index()
    {
        $this->viewClass = 'Xml';
        $this->helpers[] = 'Number';
        $this->Components->load('Cookie');
        $this->Components->load('Flash', ['className' => 'Greeting']);
        $this->Auth->authenticate = ['Form' => ['fields' => ['username' => 'email']]];
        $comments = ClassRegistry::init('Blog.Comment');
        $posts = ClassRegistry::init(['class' => 'Post', 'alias' => 'Article']);
        $this->Post->bindModel(['hasMany' => ['Tag' => ['className' => 'Post']]]);
        $email = new CakeEmail();
        $email->transport('Debug');
        $this->loadModel('Post');
        $this->loadModel('Blog.Comment');
        $this->RequestHandler->viewClassMap('json', 'Json');
        $this->RequestHandler->viewClassMap(['xml' => 'Xml']);
        // Other strings stay
        $this->set('component', 'Session');
        $this->Session->setFlash('Auth');
        $this->Paginator->paginate('Post');
    }
}
