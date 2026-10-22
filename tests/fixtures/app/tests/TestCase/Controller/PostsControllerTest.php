<?php
App::uses('PostsController', 'Controller');

class PostsControllerTest extends ControllerTestCase
{
    public $fixtures = ['app.post'];

    public function testView()
    {
        $Posts = $this->generate('Posts');
        $this->assertInstanceOf('PostsController', $Posts);
        $mock = $this->getMock('GreetingComponent', ['startup'], [new ComponentCollection()]);
        $this->assertInstanceOf('Component', $mock);
        $this->expectException('NotFoundException');
        $this->testAction('/posts/view/missing');
    }
}
