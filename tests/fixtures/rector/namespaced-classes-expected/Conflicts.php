<?php
use Cake\Network\CakeRequest;
use Acme\Network\CakeResponse;

class ResponseFactory
{
    /**
     * A short name taken by another class stays fully qualified
     *
     * @param CakeRequest $request Request
     * @param \Cake\Network\CakeResponse $default Response of CakePHP
     * @return CakeResponse|ArrayObject Response of Acme, or a global class
     */
    public function create($request, $default)
    {
        return new CakeResponse();
    }
}
