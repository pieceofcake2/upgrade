<?php

namespace App\Controller\Component;

use Cake\Controller\Controller;
use Cake\Controller\Component;

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
