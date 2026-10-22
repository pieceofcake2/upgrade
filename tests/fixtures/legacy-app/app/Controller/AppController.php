<?php
/**
 * Application level Controller
 *
 * @package       app.Controller
 */

App::uses('Controller', 'Controller');

/**
 * Application Controller
 */
class AppController extends Controller
{
    public $components = [
        'Session',
        'Greeting',
        'Auth' => [
            'authenticate' => [
                'Form' => ['passwordHasher' => 'Blowfish'],
            ],
            'authorize' => ['Controller'],
        ],
    ];

    public $helpers = ['Html', 'Form', 'Text' => ['engine' => 'CakeText']];
}
