<?php

class PostsController extends Controller
{
    public function setAction($action)
    {
        $log = function () {
            return func_get_args();
        };
        $log($action);

        return parent::setAction(...func_get_args());
    }

    public function validateErrors(...$objects)
    {
        return parent::validateErrors(...$objects);
    }
}
