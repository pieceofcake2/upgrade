<?php
namespace App\View;

use Cake\Utility\CakeTime;
use Cake\View\View;

class AppView extends View
{
    public function loadHelpers()
    {
        $this->Helpers->load('Paginator');
        $this->Helpers->load('Time', ['engine' => CakeTime::class]);
        $this->Helpers->load('Html', ['className' => 'Form']);
        parent::loadHelpers();
    }
}
