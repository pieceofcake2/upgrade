<?php

class AppModel extends Model
{
    public function query($sql, ...$params)
    {
        return parent::query($sql, ...$params);
    }

    public function isUnique($fields, $or = true, ...$moreArgs)
    {
        $args = func_get_args();

        return parent::isUnique(...$args);
    }
}
