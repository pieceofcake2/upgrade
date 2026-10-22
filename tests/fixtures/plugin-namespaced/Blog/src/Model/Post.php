<?php

namespace Blog\Model;

use Blog\Model\Behavior\PublishableBehavior;
use Cake\Model\Behavior\ContainableBehavior;

class Post extends BlogAppModel
{
    public $actsAs = [PublishableBehavior::class, ContainableBehavior::class];

    public $belongsTo = ['User'];
}
