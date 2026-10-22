<?php
/**
 * A copy of the Dispatcher of CakePHP 2.10, with a change of the application
 */

App::uses('CakeEventListener', 'Event');

class Dispatcher implements CakeEventListener
{
    /**
     * @return array
     */
    public function implementedEvents()
    {
        return ['Dispatcher.beforeDispatch' => 'parseParams'];
    }

    /**
     * @param CakeEvent $event Event
     * @return void
     */
    public function parseParams($event)
    {
        Router::parse($event->data['request']->url);
    }
}
