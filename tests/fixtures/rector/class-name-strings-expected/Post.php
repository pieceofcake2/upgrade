<?php
namespace App\Model;

use Cake\Model\Behavior\TreeBehavior;
use Cake\Model\Behavior\ContainableBehavior;

class Post extends AppModel
{
    public $actsAs = [TreeBehavior::class, ContainableBehavior::class => ['recursive' => false]];

    public $belongsTo = ['Author' => ['className' => Post::class], 'Blog.User'];

    public $hasAndBelongsToMany = [Post::class => ['with' => Post::class, 'joinTable' => 'posts_posts']];

    public function tree()
    {
        $this->Behaviors->load(TreeBehavior::class, ['left' => 'lft']);
        $this->Behaviors->attach(ContainableBehavior::class);
        $this->Behaviors->load('Ordered', ['className' => TreeBehavior::class]);
    }
}
