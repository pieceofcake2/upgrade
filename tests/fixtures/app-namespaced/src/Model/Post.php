<?php

namespace App\Model;

use App\Model\Behavior\SluggableBehavior;
use ArrayObject;
use Cake\Model\Behavior\TreeBehavior;
use Comment;

/**
 * Post
 *
 * @property Comment $Comment
 */
class Post extends AppModel
{
    public $actsAs = [SluggableBehavior::class => ['field' => 'title'], TreeBehavior::class];

    public $belongsTo = [User::class, 'Author' => ['className' => User::class]];

    public $hasAndBelongsToMany = [
        Tag::class => ['with' => PostsTag::class],
    ];

    /**
     * @param ArrayObject $options Options
     * @return array
     */
    public function published($options)
    {
        return $this->find('all', (array)$options);
    }
}
