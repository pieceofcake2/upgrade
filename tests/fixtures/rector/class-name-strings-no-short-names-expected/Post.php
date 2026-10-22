<?php
namespace App\Model;

class Post extends AppModel
{
    public $actsAs = ['Tree', 'Containable' => ['recursive' => false]];

    public $belongsTo = ['Author' => ['className' => Post::class], 'Blog.User'];

    public $hasAndBelongsToMany = [Post::class => ['with' => Post::class, 'joinTable' => 'posts_posts']];

    public function tree()
    {
        $this->Behaviors->load('Tree', ['left' => 'lft']);
        $this->Behaviors->attach('Containable');
        $this->Behaviors->load('Ordered', ['className' => 'Tree']);
    }
}
