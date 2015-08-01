<?php


namespace QMan;

require_once 'NativeFunctionStub_TestCase.php';

use Beanie\Beanie;

/**
 * Class WorkerTest
 * @package QMan
 * @covers \QMan\Worker
 */
class WorkerTest extends NativeFunctionStub_TestCase
{
    public function testConstructLocksConfig()
    {
        $configMock = $this
            ->getMockBuilder(WorkerConfig::class)
            ->setMethods(['lock'])
            ->getMock();

        $configMock
            ->expects($this->once())
            ->method('lock');

        /** @var \PHPUnit_Framework_MockObject_MockObject|Beanie $beanieMock */
        $beanieMock = $this
            ->getMockBuilder(Beanie::class)
            ->disableOriginalConstructor()
            ->getMock();


        new Worker($beanieMock, $configMock);
    }

    public function testRun()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|Beanie $beanieMock */
        $beanieMock = $this
            ->getMockBuilder(Beanie::class)
            ->disableOriginalConstructor()
            ->setMethods(['workers'])
            ->getMock();

        $workerMockBuilder = $this
            ->getMockBuilder(\Beanie\Worker::class)
            ->setMethods(['quit'])
            ->disableOriginalConstructor();

        $workers = [
            $workerMockBuilder->getMock(),
            $workerMockBuilder->getMock(),
            $workerMockBuilder->getMock(),
            $workerMockBuilder->getMock()
        ];

        $beanieMock
            ->expects($this->once())
            ->method('workers')
            ->willReturn($workers);

        array_map(function ($workerMock) {
            /** @var \PHPUnit_Framework_MockObject_MockObject|\Beanie\Worker $workerMock */
            $workerMock
                ->expects($this->once())
                ->method('quit');
        }, $workers);

        /** @var \PHPUnit_Framework_MockObject_MockObject|\QMan\EventLoop $eventLoopMock */
        $eventLoopMock = $this
            ->getMockBuilder(EventLoop::class)
            ->setMethods(['registerJobListener', 'registerBreakCondition', 'registerBreakSignal', 'run'])
            ->disableOriginalConstructor()
            ->getMock();

        $eventLoopMock
            ->expects($this->exactly(count($workers)))
            ->method('registerJobListener')
            ->withConsecutive(
                [$workers[0]],
                [$workers[1]],
                [$workers[2]],
                [$workers[3]]
            );

        $eventLoopMock
            ->expects($this->exactly(2))
            ->method('registerBreakCondition')
            ->willReturnSelf();

        $eventLoopMock
            ->expects($this->once())
            ->method('registerBreakSignal')
            ->willReturnSelf();

        $eventLoopMock
            ->expects($this->once())
            ->method('run')
            ->willReturnSelf();


        $worker = new Worker($beanieMock, null, $eventLoopMock);
        $worker->run();
    }

    public function testRemovedJobListenerCallback_schedulesReconnection()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|EventLoop $eventLoopMock */
        $eventLoopMock = $this
            ->getMockBuilder(EventLoop::class)
            ->disableOriginalConstructor()
            ->setMethods(['scheduleReconnection'])
            ->getMock();

        /** @var \PHPUnit_Framework_MockObject_MockObject|Beanie $beanieMock */
        $beanieMock = $this
            ->getMockBuilder(Beanie::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var \PHPUnit_Framework_MockObject_MockObject|\Beanie\Worker $beanieWorkerMock */
        $beanieWorkerMock = $this
            ->getMockBuilder(\Beanie\Worker::class)
            ->disableOriginalConstructor()
            ->getMock();

        $eventLoopMock
            ->expects($this->once())
            ->method('scheduleReconnection')
            ->with($this->isType('int'), $this->isType('int'), $beanieWorkerMock);


        $worker = new Worker($beanieMock, null, $eventLoopMock);
        $worker->removedJobListenerCallback($beanieWorkerMock);
    }

    public function testCheckTimeToLive_returnsTrueWhenLivingTooLong()
    {
        $startTime = time() - 1 - WorkerConfig::DEFAULT_MAX_TIME_ALIVE;

        /** @var \PHPUnit_Framework_MockObject_MockObject|Beanie $beanieMock */
        $beanieMock = $this
            ->getMockBuilder(Beanie::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var \PHPUnit_Framework_MockObject_MockObject|Worker $workerStub */
        $workerStub = $this->getMockBuilder(Worker::class)
            ->setMethods(['getStartTime'])
            ->setConstructorArgs([$beanieMock])
            ->getMock();

        $workerStub
            ->expects($this->once())
            ->method('getStartTime')
            ->willReturn($startTime);

        $this->assertTrue($workerStub->checkTimeToLive());
    }

    public function testCheckTimeToLive_returnsFalseWhenNotLivingTooLong()
    {
        $startTime = time() - (WorkerConfig::DEFAULT_MAX_TIME_ALIVE / 2);

        /** @var \PHPUnit_Framework_MockObject_MockObject|Beanie $beanieMock */
        $beanieMock = $this
            ->getMockBuilder(Beanie::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var \PHPUnit_Framework_MockObject_MockObject|Worker $workerStub */
        $workerStub = $this->getMockBuilder(Worker::class)
            ->setMethods(['getStartTime'])
            ->setConstructorArgs([$beanieMock])
            ->getMock();

        $workerStub
            ->expects($this->once())
            ->method('getStartTime')
            ->willReturn($startTime);

        $this->assertFalse($workerStub->checkTimeToLive());
    }

    public function testCheckMaximalMemoryUsage_returnsTrueWhenMemoryUsageExceedsLimits()
    {
        $this->getNativeFunctionMock(['memory_get_usage'])
            ->expects($this->once())
            ->method('memory_get_usage')
            ->willReturn(WorkerConfig::DEFAULT_MAX_MEMORY_USAGE + 1);

        /** @var \PHPUnit_Framework_MockObject_MockObject|Beanie $beanieMock */
        $beanieMock = $this
            ->getMockBuilder(Beanie::class)
            ->disableOriginalConstructor()
            ->getMock();


        $worker = new Worker($beanieMock);


        $this->assertTrue($worker->checkMaximalMemoryUsage());
    }

    public function testCheckMaximalMemoryUsage_returnsFalseWhenMemoryUsageDoesNotExceedLimits()
    {
        $this->getNativeFunctionMock(['memory_get_usage'])
            ->expects($this->once())
            ->method('memory_get_usage')
            ->willReturn(WorkerConfig::DEFAULT_MAX_MEMORY_USAGE - 1);

        /** @var \PHPUnit_Framework_MockObject_MockObject|Beanie $beanieMock */
        $beanieMock = $this
            ->getMockBuilder(Beanie::class)
            ->disableOriginalConstructor()
            ->getMock();


        $worker = new Worker($beanieMock);


        $this->assertFalse($worker->checkMaximalMemoryUsage());
    }
}
