<?php

class Post extends AppModel
{
    public function query($sql, $params = [])
    {
        return parent::query($sql, $params);
    }
}
