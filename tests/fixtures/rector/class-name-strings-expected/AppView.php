<?php
namespace App\View;

use Cake\View\Helper\PaginatorHelper;
use Cake\View\Helper\TimeHelper;
use Cake\Utility\CakeTime;
use Cake\View\Helper\FormHelper;
use Cake\View\View;

class AppView extends View
{
    public function loadHelpers()
    {
        $this->Helpers->load(PaginatorHelper::class);
        $this->Helpers->load(TimeHelper::class, ['engine' => CakeTime::class]);
        $this->Helpers->load('Html', ['className' => FormHelper::class]);
        parent::loadHelpers();
    }
}
