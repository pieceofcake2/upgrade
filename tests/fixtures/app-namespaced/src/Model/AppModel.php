<?php

namespace App\Model;

use Cake\Model\Model;
use Cake\Model\Behavior\ContainableBehavior;

class AppModel extends Model
{
    public $actsAs = [ContainableBehavior::class];
}
