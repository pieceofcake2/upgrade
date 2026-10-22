<?php

namespace App\Model\Behavior;

use Cake\Model\ModelBehavior;
use Cake\Model\Model;

class SluggableBehavior extends ModelBehavior
{
    /**
     * @param \Cake\Model\Model $model Model
     * @param array $config Settings
     * @return void
     */
    public function setup(Model $model, $config = [])
    {
        $this->settings[$model->alias] = $config;
    }
}
