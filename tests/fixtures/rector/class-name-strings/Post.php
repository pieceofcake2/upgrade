<?php
namespace App\Model;

class Post extends AppModel
{
    public $actsAs = ['Tree', 'Containable' => ['recursive' => false]];

    public $belongsTo = ['Author' => ['className' => 'Post'], 'Blog.User'];

    public $hasAndBelongsToMany = ['Post' => ['with' => 'Post', 'joinTable' => 'posts_posts']];
}
