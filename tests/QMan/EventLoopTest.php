<?php


namespace QMan;

require_once 'NativeFunctionStub_TestCase.php';

use Beanie\Exception\SocketException;
use Beanie\Job\JobOath;
use Psr\Log\NullLogger;
use Beanie\Worker as BeanieWorker;

/**
 * Class EventLoopTest
 * @package QMan
 * @covers \QMan\EventLoop
 */
class EventLoopTest extends NativeFunctionStub_TestCase
{
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

    public function testRun_startsEventLoop()
    {
        $iterations = \Ev::iteration();

        (new EventLoop())->run(\Ev::RUN_NOWAIT);

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
                ->expects($this->atLeastOnce())
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
            ->getMockBuilder(BeanieWorker::class)
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


        $eventLoop->registerJobListener($workerMock);

        /** @var \EvIo $watcher */
        $watcher = $eventLoop->getWatchers()[0];

        $this->assertInstanceOf(\EvIo::class, $watcher);
        $this->assertInternalType('resource', $watcher->fd);
        $this->assertEquals(\Ev::READ, $watcher->events);

        socket_close($socket);
    }

    public function testRegisterJobListener_triggerEvent_callsCallback()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|\Beanie\Worker $workerMock */
        $workerMock = $this
            ->getMockBuilder(BeanieWorker::class)
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
            ->expects($this->once())
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

        $this->getNativeFunctionMock(['pcntl_sigprocmask'])
            ->expects($this->exactly(2))
            ->method('pcntl_sigprocmask')
            ->withConsecutive(
                [SIG_BLOCK, []],
                [SIG_UNBLOCK, []]
            );

        $eventLoop = new EventLoop(
            new NullLogger(),
            function ($job) {
                $this->assertEquals('Job', $job);
            },
            function () {
                $this->fail('This callback should not be called');
            }
        );


        $eventLoop->registerJobListener($workerMock);
        $watcher = $eventLoop->getWatchers()[0];

        $watcher->invoke(\Ev::READ);
        socket_close($socket);
    }

    public function testRegisterJobListener_triggerEventCallbackThrowsSocketException_callsCallbackAndRemovesListener()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|\Beanie\Worker $workerMock */
        $workerMock = $this
            ->getMockBuilder(BeanieWorker::class)
            ->disableOriginalConstructor()
            ->setMethods(['reserveOath', 'disconnect'])
            ->getMock();

        $socket = socket_create_listen(0);

        /** @var \PHPUnit_Framework_MockObject_MockObject|JobOath $jobOathMock */
        $jobOathMock = $this
            ->getMockBuilder(JobOath::class)
            ->disableOriginalConstructor()
            ->setMethods(['getSocket', 'invoke'])
            ->getMock();

        $jobOathMock
            ->expects($this->once())
            ->method('getSocket')
            ->willReturn($socket);

        $jobOathMock
            ->expects($this->once())
            ->method('invoke')
            ->willReturn('Job');

        $workerMock
            ->expects($this->once())
            ->method('reserveOath')
            ->willReturn($jobOathMock);

        $workerMock
            ->expects($this->once())
            ->method('disconnect');

        $callbackCalled = false;

        $eventLoop = new EventLoop(
            new NullLogger(),
            function ($job) {
                $this->assertEquals('Job', $job);
                throw new SocketException('go');
            },
            function () use (&$callbackCalled) {
                $callbackCalled = true;
            }
        );


        $eventLoop->registerJobListener($workerMock);
        $watcher = $eventLoop->getWatchers()[0];

        $watcher->invoke(\Ev::READ);

        if (!$callbackCalled) {
            $this->fail('Callback had to be called');
        }

        socket_close($socket);
    }

    public function testRegisterJobListener_triggerEventCallbackThrowsGenericException_callsCallback()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|\Beanie\Worker $workerMock */
        $workerMock = $this
            ->getMockBuilder(BeanieWorker::class)
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
            ->expects($this->once())
            ->method('getSocket')
            ->willReturn($socket);

        $jobOathMock
            ->expects($this->once())
            ->method('invoke')
            ->willReturn('Job');

        $workerMock
            ->expects($this->once())
            ->method('reserveOath')
            ->willReturn($jobOathMock);


        $eventLoop = new EventLoop(
            new NullLogger(),
            function ($job) {
                $this->assertEquals('Job', $job);
                throw new \RuntimeException('go');
            },
            function () {
                $this->fail('should not get called');
            }
        );


        $eventLoop->registerJobListener($workerMock);
        $watcher = $eventLoop->getWatchers()[0];

        $watcher->invoke(\Ev::READ);

        socket_close($socket);
    }

    public function testDestructor_stopsAllWatchers()
    {
        $watchers = [];
        $watchers[] = $this->getWatcherMock(['stop']);
        $watchers[] = $this->getWatcherMock(['stop']);
        $watchers[] = $this->getWatcherMock(['stop']);

        $eventLoop = new EventLoop();

        array_map(function ($watcher) use ($eventLoop) {
            /** @var \PHPUnit_Framework_MockObject_MockObject|WatcherMock $watcher */
            $watcher
                ->expects($this->atLeastOnce())
                ->method('stop')
                ->willReturn(true);

            $eventLoop->registerWatcher($watcher);
        }, $watchers);


        $eventLoop->__destruct();
    }

    public function testAttemptReconnection_successFulAttempt_removesWatcherCreatesJobListener()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|EventLoop $eventLoopStub */
        $eventLoopStub = $this
            ->getMockBuilder(EventLoop::class)
            ->disableOriginalConstructor()
            ->setMethods(['removeWatcher', 'registerJobListener'])
            ->getMock();

        $eventLoopStub->setLogger(new NullLogger());

        $watcher = $this->getWatcherMock();

        /** @var \PHPUnit_Framework_MockObject_MockObject|\Beanie\Worker $workerMock */
        $workerMock = $this
            ->getMockBuilder(BeanieWorker::class)
            ->disableOriginalConstructor()
            ->setMethods(['reconnect'])
            ->getMock();

        $eventLoopStub
            ->expects($this->once())
            ->method('removeWatcher')
            ->with($watcher);

        $eventLoopStub
            ->expects($this->once())
            ->method('registerJobListener')
            ->with($workerMock);


        $eventLoopStub->attemptReconnection($watcher, $workerMock);
    }

    public function testAttemptReconnection_exceptionOccurs_watcherNotRemoved_jobListenerNotCreated()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|EventLoop $eventLoopStub */
        $eventLoopStub = $this
            ->getMockBuilder(EventLoop::class)
            ->disableOriginalConstructor()
            ->setMethods(['removeWatcher', 'registerJobListener'])
            ->getMock();

        $eventLoopStub->setLogger(new NullLogger());

        $watcher = $this->getWatcherMock();

        /** @var \PHPUnit_Framework_MockObject_MockObject|\Beanie\Worker $workerMock */
        $workerMock = $this
            ->getMockBuilder(BeanieWorker::class)
            ->disableOriginalConstructor()
            ->setMethods(['reconnect'])
            ->getMock();

        $workerMock
            ->expects($this->once())
            ->method('reconnect')
            ->willThrowException(new SocketException());

        $eventLoopStub
            ->expects($this->never())
            ->method('removeWatcher');

        $eventLoopStub
            ->expects($this->never())
            ->method('registerJobListener');


        $eventLoopStub->attemptReconnection($watcher, $workerMock);
    }

    public function testScheduleReconnection_registersTimerWithCallback()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|\Beanie\Worker $workerMock */
        $workerMock = $this
            ->getMockBuilder(BeanieWorker::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var \PHPUnit_Framework_MockObject_MockObject|EventLoop $eventLoopStub */
        $eventLoopStub = $this
            ->getMockBuilder(EventLoop::class)
            ->disableOriginalConstructor()
            ->setMethods(['attemptReconnection'])
            ->getMock();

        $after = 5;
        $every = 20;


        $eventLoopStub->scheduleReconnection($after, $every, $workerMock);
        $timerWatcher = $eventLoopStub->getWatchers()[0];


        $this->assertInstanceOf(\EvTimer::class, $timerWatcher);
        $this->assertAttributeEquals($after, 'remaining', $timerWatcher);
        $this->assertAttributeEquals($every, 'repeat', $timerWatcher);

        $eventLoopStub
            ->expects($this->once())
            ->method('attemptReconnection')
            ->with($timerWatcher, $workerMock);

        $timerWatcher->invoke(\Ev::TIMER);
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
}

class WatcherMock extends \EvWatcher
{
    public function __construct() {}
}
