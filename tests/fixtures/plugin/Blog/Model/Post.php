<?php
App::uses('BlogAppModel', 'Blog.Model');

class Post extends BlogAppModel
{
    public $actsAs = ['Blog.Publishable', 'Containable'];

    public $belongsTo = ['User'];
}
