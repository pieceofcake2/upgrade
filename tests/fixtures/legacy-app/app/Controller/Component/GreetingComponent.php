<?php
App::uses('Component', 'Controller');

class GreetingComponent extends Component
{
    /**
     * @param Controller $controller Controller
     * @return void
     */
    public function startup(Controller $controller)
    {
        $controller->set('greeting', __('Hello'));
    }
}
