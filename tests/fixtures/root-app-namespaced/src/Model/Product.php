<?php

namespace App\Model;

use Cake\Model\Behavior\ContainableBehavior;

class Product extends AppModel
{
    public $actsAs = [ContainableBehavior::class];
}
