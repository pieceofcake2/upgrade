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
