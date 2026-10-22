<?php
namespace App\Controller;

use App\Model\Post;
use Cake\Utility\CakeText;
use Cake\Network\Email\CakeEmail;
use Cake\Utility\ClassRegistry;

class PostsController extends AppController
{
    public $uses = [Post::class, 'Blog.Comment'];

    public $components = [
        'Session',
        'Greeting',
        'Mailer' => ['className' => 'Greeting'],
        'DebugKit.Toolbar',
        'Auth' => [
            'authenticate' => [
                'all' => ['passwordHasher' => 'Blowfish'],
                'Form',
                'Basic' => ['passwordHasher' => ['className' => 'Simple', 'hashType' => 'sha256']],
            ],
            'authorize' => 'Controller',
        ],
    ];

    public $helpers = ['Html', 'Text' => ['engine' => CakeText::class], 'Js' => ['Jquery']];

    public $viewClass = 'Json';

    public function index()
    {
        $this->viewClass = 'Xml';
        $this->helpers[] = 'Number';
        $this->Components->load('Cookie');
        $this->Components->load('Flash', ['className' => 'Greeting']);
        $this->Auth->authenticate = ['Form' => ['fields' => ['username' => 'email']]];
        $comments = ClassRegistry::init('Blog.Comment');
        $posts = ClassRegistry::init(['class' => Post::class, 'alias' => 'Article']);
        $this->Post->bindModel(['hasMany' => ['Tag' => ['className' => Post::class]]]);
        $email = new CakeEmail();
        $email->transport('Debug');
        // Other strings stay
        $this->set('component', 'Session');
        $this->Session->setFlash('Auth');
        $this->loadModel('Post');
    }
}
