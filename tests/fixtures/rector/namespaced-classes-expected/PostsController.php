<?php
use Cake\Controller\Controller;
use Cake\Network\CakeResponse;
use Cake\Core\App;
use Cake\Network\CakeRequest;
use Cake\Model\Model;
use Cake\Controller\Component\SessionComponent;
use Cake\Controller\Component\AuthComponent;
use Cake\Error\NotFoundException;
use Cake\Error\BadRequestException;
use Cake\Error\MethodNotAllowedException;
use Cake\Event\CakeEventManager;
use Cake\Error\ForbiddenException;
use Cake\Error\UnauthorizedException;
use Cake\Controller\Component;
use Cake\Network\Email\CakeEmail;
use Cake\Core\Configure;
use Cake\Utility\Hash;
use Cake\Log\CakeLog;
use Cake\Utility\Inflector;

App::uses('AppController', 'Controller');

class PostsController extends AppController
{
    public function beforeFilter()
    {
        if ($this->request instanceof CakeRequest) {
            $this->set('plain', true);
        }
        if (CakeResponse::class !== get_class($this->response)) {
            $this->set('custom', true);
        }
        if (get_parent_class($this) == 'AppController') {
            $this->set('app', true);
        }
        if ($this->Post::class != Model::class) {
            $this->set('model', true);
        }
        $this->set('session', in_array(get_class($this->Session), [SessionComponent::class, 'MySessionComponent'], true));
        if (!$this->Auth instanceof AuthComponent) {
            $this->set('auth', false);
        }
        if (get_class($this->Session) == SessionComponent::class) {
            $this->set('session', true);
        }
    }

    public function view($id)
    {
        try {
            $this->Post->get($id);
        } catch (Exception $e) {
            switch (get_class($e)) {
                case NotFoundException::class:
                    return $this->redirect('/');
                case 'MyException':
                    return null;
            }

            if (in_array(get_class($e), [BadRequestException::class, MethodNotAllowedException::class])) {
                return $this->redirect('/');
            }
            if (in_array(get_class($this->getEventManager()), [CakeEventManager::class])) {
                return null;
            }

            return match (get_class($e)) {
                ForbiddenException::class, UnauthorizedException::class => $this->redirect('/login'),
                default => throw $e,
            };
        }
    }

    public function unchanged()
    {
        $this->set('instance', $this->request instanceof CakeRequest);
        $this->set('string', 'CakeRequest');
        $this->set('model', get_class($this->Post) === 'Post');

        return static::class === Controller::class;
    }
}

class MySessionComponent extends SessionComponent
{
}

class GreetingComponent extends Component
{
    /**
     * @param Controller $controller Controller
     * @return CakeResponse|null
     */
    public function startup(Controller $controller)
    {
        $email = new CakeEmail('default');
        Configure::write('Greeting', Hash::get(['a' => 1], 'a'));
        CakeLog::write('debug', Inflector::camelize('a_b'));

        return null;
    }
}
