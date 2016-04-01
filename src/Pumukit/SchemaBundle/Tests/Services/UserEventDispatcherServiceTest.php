<?php

namespace Pumukit\SchemaBundle\Tests\Services;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\SchemaBundle\Event\SchemaEvents;
use Pumukit\SchemaBundle\Event\UserEvent;
use Pumukit\SchemaBundle\Services\UserEventDispatcherService;

class UserEventDispatcherServiceTest extends WebTestCase
{
    const EMPTY_NAME = 'EMTPY_NAME';

    private $dm;
    private $userDispatcher;

    public function __construct()
    {
        $options = array('environment' => 'test');
        static::bootKernel($options);

        $this->dm = static::$kernel->getContainer()
          ->get('doctrine_mongodb.odm.document_manager');
        $this->dispatcher = new EventDispatcher();
        
    }

    public function setUp()
    {
        $this->dm->getDocumentCollection('PumukitSchemaBundle:User')->remove(array());
        $this->dm->flush();

        MockUpUserListener::$called = false;
        MockUpUserListener::$name = UserEventDispatcherServiceTest::EMPTY_NAME;

        $this->userDispatcher = new UserEventDispatcherService($this->dispatcher);
    }

    public function testDispatchCreate()
    {
        $this->dispatcher->addListener(SchemaEvents::USER_CREATE, function($event, $name)
                                       {
                                           $this->assertTrue($event instanceof UserEvent);
                                           $this->assertEquals(SchemaEvents::USER_CREATE, $name);

                                           $user = $event->getUser();

                                           MockUpUserListener::$called = true;
                                           MockUpUserListener::$name = $user->getUsername();
                                       });

        $this->assertFalse(MockUpUserListener::$called);
        $this->assertEquals(UserEventDispatcherServiceTest::EMPTY_NAME, MockUpUserListener::$name);

        $name = 'test_name';

        $user = new User();
        $user->setUsername($name);

        $this->dm->persist($user);
        $this->dm->flush();

        $this->userDispatcher->dispatchCreate($user);

        $this->assertTrue(MockUpUserListener::$called);
        $this->assertEquals($name, MockUpUserListener::$name);
    }

    public function testDispatchUpdate()
    {
        $this->dispatcher->addListener(SchemaEvents::USER_UPDATE, function($event, $name)
                                       {
                                           $this->assertTrue($event instanceof UserEvent);
                                           $this->assertEquals(SchemaEvents::USER_UPDATE, $name);

                                           $user = $event->getUser();

                                           MockUpUserListener::$called = true;
                                           MockUpUserListener::$name = $user->getUsername();
                                       });

        $this->assertFalse(MockUpUserListener::$called);
        $this->assertEquals(UserEventDispatcherServiceTest::EMPTY_NAME, MockUpUserListener::$name);

        $name = 'test_name';

        $user = new User();
        $user->setUsername($name);

        $this->dm->persist($user);
        $this->dm->flush();

        $updateUsername = 'New_name';
        $user->setUsername($updateUsername);

        $this->dm->persist($user);
        $this->dm->flush();

        $this->userDispatcher->dispatchUpdate($user);

        $this->assertTrue(MockUpUserListener::$called);
        $this->assertEquals($updateUsername, MockUpUserListener::$name);
    }

    public function testDispatchDelete()
    {
        $this->dispatcher->addListener(SchemaEvents::USER_DELETE, function($event, $name)
                                       {
                                           $this->assertTrue($event instanceof UserEvent);
                                           $this->assertEquals(SchemaEvents::USER_DELETE, $name);

                                           $user = $event->getUser();

                                           MockUpUserListener::$called = true;
                                           MockUpUserListener::$name = $user->getUsername();
                                       });

        $this->assertFalse(MockUpUserListener::$called);
        $this->assertEquals(UserEventDispatcherServiceTest::EMPTY_NAME, MockUpUserListener::$name);

        $name = 'test_name';

        $user = new User();
        $user->setUsername($name);

        $this->dm->persist($user);
        $this->dm->flush();

        $this->userDispatcher->dispatchDelete($user);

        $this->assertTrue(MockUpUserListener::$called);
        $this->assertEquals($name, MockUpUserListener::$name);
    }
}

class MockUpUserListener
{
    static public $called = false;
    static public $name = UserEventDispatcherServiceTest::EMPTY_NAME;
}