<?php

class Post extends AppModel
{
    public function query($sql, $params = [], ...$args)
    {
        return parent::query($sql, $params);
    }
}
