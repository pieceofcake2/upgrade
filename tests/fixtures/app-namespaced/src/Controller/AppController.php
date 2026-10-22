<?php
/**
 * Application level Controller
 *
 * @package       app.Controller
 */

namespace App\Controller;

use Cake\Controller\Controller;
use Cake\Controller\Component\SessionComponent;
use App\Controller\Component\GreetingComponent;
use Cake\Controller\Component\AuthComponent;
use Cake\Controller\Component\Auth\FormAuthenticate;
use Cake\Controller\Component\Auth\BlowfishPasswordHasher;
use Cake\Controller\Component\Auth\ControllerAuthorize;
use Cake\View\Helper\HtmlHelper;
use Cake\View\Helper\FormHelper;
use Cake\View\Helper\TextHelper;
use Cake\Utility\CakeText;

/**
 * Application Controller
 */
class AppController extends Controller
{
    public $components = [
        SessionComponent::class,
        GreetingComponent::class,
        AuthComponent::class => [
            'authenticate' => [
                FormAuthenticate::class => ['passwordHasher' => BlowfishPasswordHasher::class],
            ],
            'authorize' => [ControllerAuthorize::class],
        ],
    ];

    public $helpers = [HtmlHelper::class, FormHelper::class, TextHelper::class => ['engine' => CakeText::class]];
}
