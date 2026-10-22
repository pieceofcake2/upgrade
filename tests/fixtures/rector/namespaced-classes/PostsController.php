<?php
App::uses('AppController', 'Controller');

class PostsController extends AppController
{
    public function beforeFilter()
    {
        if (get_class($this->request) === 'CakeRequest') {
            $this->set('plain', true);
        }
        if ('CakeResponse' !== get_class($this->response)) {
            $this->set('custom', true);
        }
        if (get_parent_class($this) == 'AppController') {
            $this->set('app', true);
        }
        if ($this->Post::class != '\Model') {
            $this->set('model', true);
        }
        $this->set('session', in_array(get_class($this->Session), ['SessionComponent', 'MySessionComponent'], true));
        if (get_class($this->Auth) !== 'AuthComponent') {
            $this->set('auth', false);
        }
        if (get_class($this->Session) == 'SessionComponent') {
            $this->set('session', true);
        }
    }

    public function view($id)
    {
        try {
            $this->Post->get($id);
        } catch (Exception $e) {
            switch (get_class($e)) {
                case 'NotFoundException':
                    return $this->redirect('/');
                case 'MyException':
                    return null;
            }

            if (in_array(get_class($e), ['BadRequestException', 'MethodNotAllowedException'])) {
                return $this->redirect('/');
            }
            if (in_array(get_class($this->getEventManager()), ['CakeEventManager'])) {
                return null;
            }

            return match (get_class($e)) {
                'ForbiddenException', 'UnauthorizedException' => $this->redirect('/login'),
                default => throw $e,
            };
        }
    }

    public function unchanged()
    {
        $this->set('instance', $this->request instanceof CakeRequest);
        $this->set('string', 'CakeRequest');
        $this->set('model', get_class($this->Post) === 'Post');

        return static::class === 'Controller';
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
