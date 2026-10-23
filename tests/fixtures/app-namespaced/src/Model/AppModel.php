<?php

namespace App\Model;

use Cake\Model\Behavior\ContainableBehavior;
use Cake\Model\Model;

class AppModel extends Model
{
    public $actsAs = [ContainableBehavior::class];
}
