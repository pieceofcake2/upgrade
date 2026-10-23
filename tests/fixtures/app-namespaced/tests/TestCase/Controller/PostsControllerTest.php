<?php

namespace App\Test\TestCase\Controller;

use App\Controller\Component\GreetingComponent;
use App\Controller\PostsController;
use Cake\Controller\Component;
use Cake\Controller\ComponentCollection;
use Cake\Error\NotFoundException;
use Cake\TestSuite\ControllerTestCase;

class PostsControllerTest extends ControllerTestCase
{
    public $fixtures = ['app.post'];

    public function testView()
    {
        $Posts = $this->generate('Posts');
        $this->assertInstanceOf(PostsController::class, $Posts);
        $mock = $this->getMock(GreetingComponent::class, ['startup'], [new ComponentCollection()]);
        $this->assertInstanceOf(Component::class, $mock);
        $this->expectException(NotFoundException::class);
        $this->testAction('/posts/view/missing');
    }
}
