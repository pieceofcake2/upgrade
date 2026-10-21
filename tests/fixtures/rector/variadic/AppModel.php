<?php

class AppModel extends Model
{
    public function query($sql)
    {
        return parent::query(...func_get_args());
    }

    public function isUnique($fields, $or = true)
    {
        $args = func_get_args();

        return parent::isUnique(...$args);
    }
}
