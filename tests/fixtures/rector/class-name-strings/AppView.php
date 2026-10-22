<?php
namespace App\View;

use Cake\View\View;

class AppView extends View
{
    public function loadHelpers()
    {
        $this->Helpers->load('Paginator');
        $this->Helpers->load('Time', ['engine' => 'CakeTime']);
        $this->Helpers->load('Html', ['className' => 'Form']);
        parent::loadHelpers();
    }
}
