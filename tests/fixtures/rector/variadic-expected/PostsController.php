<?php

class PostsController extends Controller
{
    public function setAction($action, ...$args)
    {
        $log = function () {
            return func_get_args();
        };
        $log($action);

        return parent::setAction($action, ...$args);
    }

    public function validateErrors(...$objects)
    {
        return parent::validateErrors(...$objects);
    }
}
