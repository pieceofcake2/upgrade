<?php

namespace App\Model\Behavior;

use Cake\Model\Model;
use Cake\Model\ModelBehavior;

class SluggableBehavior extends ModelBehavior
{
    /**
     * @param Model $model Model
     * @param array $config Settings
     * @return void
     */
    public function setup(Model $model, $config = [])
    {
        $this->settings[$model->alias] = $config;
    }
}
