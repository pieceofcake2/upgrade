<?php
App::uses('AppModel', 'Model');

/**
 * Post
 *
 * @property Comment $Comment
 */
class Post extends AppModel
{
    public $actsAs = ['Sluggable' => ['field' => 'title'], 'Tree'];

    public $belongsTo = ['User', 'Author' => ['className' => 'User']];

    public $hasAndBelongsToMany = [
        'Tag' => ['with' => 'PostsTag'],
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
