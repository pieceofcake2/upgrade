<?php

class Unrelated
{
    public function query($sql)
    {
        return func_get_args();
    }
}
