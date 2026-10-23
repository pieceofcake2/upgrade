<?php

namespace App\Controller;

use Cake\Controller\Controller;
use Cake\View\Helper\FormHelper;
use Cake\View\Helper\HtmlHelper;

/**
 * Application Controller
 */
class AppController extends Controller
{
    public $helpers = [HtmlHelper::class, FormHelper::class];
}
