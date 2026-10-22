<?php

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\ControllerTestCase;
use App\Controller\PostsController;
use App\Controller\Component\GreetingComponent;
use Cake\Controller\ComponentCollection;
use Cake\Controller\Component;
use Cake\Error\NotFoundException;

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
