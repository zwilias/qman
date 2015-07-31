<?php


namespace QMan;



use Beanie\Job\JobOath;

class EventLoopTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Exception
     */
    public function testConstruct_evExtensionNotLoaded_throwsException()
    {
        $this->getNativeFunctionMock()
            ->expects($this->once())
            ->method('extension_loaded')
            ->with('ev')
            ->willReturn(false);

        new EventLoop();
    }

    public function testRegisterWatcher_savesWatcher()
    {
        $watcher = $this->getWatcherMock();
        $eventLoop = new EventLoop();


        $eventLoop->registerWatcher($watcher);


        $this->assertThat($eventLoop->getWatchers(), $this->contains($watcher));
    }

    public function testRemoveWatcher_removesWatcher()
    {
        $watcher = $this->getWatcherMock(['stop']);

        $watcher
            ->expects($this->once())
            ->method('stop')
            ->willReturn(true);

        $eventLoop = new EventLoop();


        $eventLoop->registerWatcher($watcher);
        $eventLoop->removeWatcher($watcher);


        $this->assertThat($eventLoop->getWatchers(), $this->logicalNot($this->contains($watcher)));
    }

    public function testRemoveWatcher_removedWatcher_doesNothing()
    {
        $watcher = $this->getWatcherMock();
        $eventLoop = new EventLoop();


        $eventLoop->removeWatcher($watcher);


        $this->assertThat($eventLoop->getWatchers(), $this->logicalNot($this->contains($watcher)));
    }

    public function testRegisterBreakCondition_callbackReturnsTrue_eventLoopIsStopped()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|EventLoop $eventLoop */
        $eventLoop = $this->getMockBuilder(EventLoop::class)
            ->setMethods(['stop'])
            ->getMock();

        $eventLoop
            ->expects($this->once())
            ->method('stop')
            ->willReturnSelf();

        $callback = function () {
            return true;
        };

        $eventLoop->registerBreakCondition('test', $callback);

        $watcher = $eventLoop->getWatchers()[0];

        $watcher->invoke(\Ev::TIMER);
    }

    public function testRegisterBreakCondition_callbackReturnFalse_eventLoopIsNotStopped()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|EventLoop $eventLoop */
        $eventLoop = $this
            ->getMockBuilder(EventLoop::class)
            ->setMethods(['stop'])
            ->getMock();

        $eventLoop
            ->expects($this->never())
            ->method('stop');

        $callback = function () {
            return false;
        };

        $eventLoop->registerBreakCondition('test', $callback);

        $watcher = $eventLoop->getWatchers()[0];

        $watcher->invoke(\Ev::TIMER);
    }

    public function testRegisterBreakCondition_multipleConditions_onlySingleWatcherIsRegistered()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|EventLoop $eventLoop */
        $eventLoop = $this->getMockBuilder(EventLoop::class)
            ->setMethods(['registerWatcher'])
            ->getMock();

        $eventLoop->expects($this->once())
            ->method('registerWatcher')
            ->with($this->isInstanceOf(\EvTimer::class));


        $eventLoop->registerBreakCondition('test1', function () {});
        $eventLoop->registerBreakCondition('test2', function () {});
    }

    public function testRegisterBreakSignal_registersSignalWatcher()
    {
        $signal = SIGUSR1;

        /** @var \PHPUnit_Framework_MockObject_MockObject|EventLoop $eventLoop */
        $eventLoop = $this->getMockBuilder(EventLoop::class)
            ->setMethods(['registerWatcher'])
            ->getMock();

        $eventLoop
            ->expects($this->once())
            ->method('registerWatcher')
            ->with($this->logicalAnd(
                $this->isInstanceOf(\EvSignal::class),
                $this->objectHasAttribute('signum'),
                $this->attributeEqualTo('signum', $signal)
            ));


        $eventLoop->registerBreakSignal($signal);
    }

    public function testRegisterBreakSignal_triggerSignal_stopsEventLoop()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|EventLoop $eventLoop */
        $eventLoop = $this->getMockBuilder(EventLoop::class)
            ->setMethods(['stop'])
            ->getMock();

        $eventLoop
            ->expects($this->once())
            ->method('stop')
            ->willReturnSelf();


        $eventLoop->registerBreakSignal(SIGUSR1);


        $signalWatcher = $eventLoop->getWatchers()[0];
        $signalWatcher->invoke(\Ev::SIGNAL);
    }

    public function testRun_startEventLoop()
    {
        $iterations = \Ev::iteration();

        (new EventLoop())->run(\Ev::RUN_ONCE);

        $this->assertThat(\Ev::iteration(), $this->greaterThan($iterations));
    }

    public function testStop_stopsAllWatchers()
    {
        $watchers = [];
        $watchers[] = $this->getWatcherMock(['stop']);
        $watchers[] = $this->getWatcherMock(['stop']);
        $watchers[] = $this->getWatcherMock(['stop']);

        $eventLoop = new EventLoop();

        array_map(function ($watcher) use ($eventLoop) {
            /** @var \PHPUnit_Framework_MockObject_MockObject|WatcherMock $watcher */
            $watcher
                ->expects($this->once())
                ->method('stop')
                ->willReturn(true);

            $eventLoop->registerWatcher($watcher);
        }, $watchers);


        $eventLoop->stop();
    }

    public function testRegisterJobListener_registersIoWatcherWithSocket()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|\Beanie\Worker $workerMock */
        $workerMock = $this
            ->getMockBuilder(\Beanie\Worker::class)
            ->disableOriginalConstructor()
            ->setMethods(['reserveOath'])
            ->getMock();

        $socket = socket_create_listen(0);

        /** @var \PHPUnit_Framework_MockObject_MockObject|JobOath $jobOathMock */
        $jobOathMock = $this
            ->getMockBuilder(JobOath::class)
            ->disableOriginalConstructor()
            ->setMethods(['getSocket'])
            ->getMock();

        $jobOathMock
            ->expects($this->once())
            ->method('getSocket')
            ->willReturn($socket);

        $workerMock
            ->expects($this->once())
            ->method('reserveOath')
            ->willReturn($jobOathMock);


        $eventLoop = new EventLoop();


        $eventLoop->registerJobListener($workerMock, function () {
            $this->fail('Not supposed to get triggered');
        });

        /** @var \EvIo $watcher */
        $watcher = $eventLoop->getWatchers()[0];

        $this->assertInstanceOf(\EvIo::class, $watcher);
        $this->assertInternalType('resource', $watcher->fd);
        $this->assertEquals(\Ev::READ, $watcher->events);

        $eventLoop->removeWatcher($watcher);
        unset($watcher);

        socket_close($socket);
    }

    public function testRegisterJobListener_triggerEvent_callsCallbackAndCreatesNewListener()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|\Beanie\Worker $workerMock */
        $workerMock = $this
            ->getMockBuilder(\Beanie\Worker::class)
            ->disableOriginalConstructor()
            ->setMethods(['reserveOath'])
            ->getMock();

        $socket = socket_create_listen(0);

        /** @var \PHPUnit_Framework_MockObject_MockObject|JobOath $jobOathMock */
        $jobOathMock = $this
            ->getMockBuilder(JobOath::class)
            ->disableOriginalConstructor()
            ->setMethods(['getSocket', 'invoke'])
            ->getMock();

        $jobOathMock
            ->expects($this->exactly(2))
            ->method('getSocket')
            ->willReturn($socket);

        $jobOathMock
            ->expects($this->once())
            ->method('invoke')
            ->willReturn('Job');

        $workerMock
            ->expects($this->exactly(2))
            ->method('reserveOath')
            ->willReturn($jobOathMock);


        $eventLoop = new EventLoop();


        $eventLoop->registerJobListener($workerMock, function ($job) {
            $this->assertEquals('Job', $job);
        });
        $watcher = $eventLoop->getWatchers()[0];

        $watcher->invoke(\Ev::READ);

        foreach ($eventLoop->getWatchers() as $watcher) {
            $watcher->stop();
            $eventLoop->removeWatcher($watcher);
            unset($watcher);
        };

        socket_close($socket);
    }

    public function testRegisterJobListener_triggerEventCallbackThrowsException_callsCallbackAndCreatesNewListener()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|\Beanie\Worker $workerMock */
        $workerMock = $this
            ->getMockBuilder(\Beanie\Worker::class)
            ->disableOriginalConstructor()
            ->setMethods(['reserveOath'])
            ->getMock();

        $socket = socket_create_listen(0);

        /** @var \PHPUnit_Framework_MockObject_MockObject|JobOath $jobOathMock */
        $jobOathMock = $this
            ->getMockBuilder(JobOath::class)
            ->disableOriginalConstructor()
            ->setMethods(['getSocket', 'invoke'])
            ->getMock();

        $jobOathMock
            ->expects($this->exactly(2))
            ->method('getSocket')
            ->willReturn($socket);

        $jobOathMock
            ->expects($this->once())
            ->method('invoke')
            ->willReturn('Job');

        $workerMock
            ->expects($this->exactly(2))
            ->method('reserveOath')
            ->willReturn($jobOathMock);


        $eventLoop = new EventLoop();


        $eventLoop->registerJobListener($workerMock, function ($job) {
            $this->assertEquals('Job', $job);
            throw new \RuntimeException('handle this');
        });
        $watcher = $eventLoop->getWatchers()[0];

        $watcher->invoke(\Ev::READ);

        foreach ($eventLoop->getWatchers() as $watcher) {
            $watcher->stop();
            $eventLoop->removeWatcher($watcher);
            unset($watcher);
        }

        socket_close($socket);
    }

    public function tearDown()
    {
        global $nativeFunctionMock;
        $nativeFunctionMock = null;
    }

    /**
     * @param array $extraMethods
     * @return \PHPUnit_Framework_MockObject_MockObject|WatcherMock
     */
    protected function getWatcherMock($extraMethods = [])
    {
        return $this
            ->getMockBuilder(WatcherMock::class)
            ->setMethods($extraMethods)
            ->getMock();
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getNativeFunctionMock()
    {
        global $nativeFunctionMock, $mockedNativeFunctions;

        if (! isset($nativeFunctionMock)) {
            $nativeFunctionMock = $this
                ->getMockBuilder('stdClass')
                ->setMethods($mockedNativeFunctions)
                ->getMock()
            ;
        }

        return $nativeFunctionMock;
    }
}

class WatcherMock extends \EvWatcher
{
    public function __construct() {}
}

$nativeFunctionMock = null;
$mockedNativeFunctions = [
    'extension_loaded',
    'pcntl_sigprocmask'
];

$namespace = __NAMESPACE__;

foreach ($mockedNativeFunctions as $mockedFunction) {
    eval(<<<EOD
namespace {$namespace};

function {$mockedFunction}()
{
    global \$nativeFunctionMock;
    return is_callable([\$nativeFunctionMock, '{$mockedFunction}'])
        ? call_user_func_array([\$nativeFunctionMock, '{$mockedFunction}'], func_get_args())
        : call_user_func_array('{$mockedFunction}', func_get_args());
}
EOD
    );
}
