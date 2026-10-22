<?php
namespace App\Controller;

use App\Model\Post;
use Cake\Controller\Component\SessionComponent;
use App\Controller\Component\GreetingComponent;
use Cake\Controller\Component\AuthComponent;
use Cake\Controller\Component\Auth\BlowfishPasswordHasher;
use Cake\Controller\Component\Auth\FormAuthenticate;
use Cake\Controller\Component\Auth\BasicAuthenticate;
use Cake\Controller\Component\Auth\SimplePasswordHasher;
use Cake\Controller\Component\Auth\ControllerAuthorize;
use Cake\View\Helper\HtmlHelper;
use Cake\View\Helper\TextHelper;
use Cake\Utility\CakeText;
use Cake\View\Helper\JsHelper;
use Cake\View\JsonView;
use Cake\View\XmlView;
use Cake\View\Helper\NumberHelper;
use Cake\Controller\Component\CookieComponent;
use Cake\Network\Email\DebugTransport;
use Cake\Network\Email\CakeEmail;
use Cake\Utility\ClassRegistry;

class PostsController extends AppController
{
    public $uses = [Post::class, 'Blog.Comment'];

    public $components = [
        SessionComponent::class,
        GreetingComponent::class,
        'Mailer' => ['className' => GreetingComponent::class],
        'DebugKit.Toolbar',
        AuthComponent::class => [
            'authenticate' => [
                'all' => ['passwordHasher' => BlowfishPasswordHasher::class],
                FormAuthenticate::class,
                BasicAuthenticate::class => ['passwordHasher' => ['className' => SimplePasswordHasher::class, 'hashType' => 'sha256']],
            ],
            'authorize' => ControllerAuthorize::class,
        ],
    ];

    public $helpers = [HtmlHelper::class, TextHelper::class => ['engine' => CakeText::class], JsHelper::class => ['Jquery']];

    public $viewClass = JsonView::class;

    public function index()
    {
        $this->viewClass = XmlView::class;
        $this->helpers[] = NumberHelper::class;
        $this->Components->load(CookieComponent::class);
        $this->Components->load('Flash', ['className' => GreetingComponent::class]);
        $this->Auth->authenticate = [FormAuthenticate::class => ['fields' => ['username' => 'email']]];
        $comments = ClassRegistry::init('Blog.Comment');
        $posts = ClassRegistry::init(['class' => Post::class, 'alias' => 'Article']);
        $this->Post->bindModel(['hasMany' => ['Tag' => ['className' => Post::class]]]);
        $email = new CakeEmail();
        $email->transport(DebugTransport::class);
        // Other strings stay
        $this->set('component', 'Session');
        $this->Session->setFlash('Auth');
        $this->loadModel('Post');
    }
}
