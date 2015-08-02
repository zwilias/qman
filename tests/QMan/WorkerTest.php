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
        /** @var \PHPUnit_Framework_MockObject_MockObject|QManConfig $configMock */
        $configMock = $this
            ->getMockBuilder(QManConfig::class)
            ->setMethods(['lock'])
            ->getMock();

        $configMock
            ->expects($this->atLeastOnce())
            ->method('lock');

        /** @var \PHPUnit_Framework_MockObject_MockObject|Beanie $beanieMock */
        $beanieMock = $this
            ->getMockBuilder(Beanie::class)
            ->disableOriginalConstructor()
            ->getMock();


        (new WorkerBuilder())
            ->withQManConfig($configMock)
            ->build($beanieMock);
    }

    public function testRun_registersListeners_runsEventLoop_quitsAllWorkers_ignoresQuitException()
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
                ->method('quit')
                ->willThrowException(new \RuntimeException());
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


        $worker = (new WorkerBuilder())
            ->withEventLoop($eventLoopMock)
            ->build($beanieMock);
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


        $worker = (new WorkerBuilder())->withEventLoop($eventLoopMock)->build($beanieMock);
        $worker->removedJobListenerCallback($beanieWorkerMock);
    }

    public function testCheckTimeToLive_returnsTrueWhenLivingTooLong()
    {
        $this->getNativeFunctionMock(['time'])
            ->expects($this->exactly(2))
            ->method('time')
            ->willReturnOnConsecutiveCalls(
                (\time() - QManConfig::DEFAULT_MAX_TIME_ALIVE - 1),
                (\time())
            );


        /** @var \PHPUnit_Framework_MockObject_MockObject|Beanie $beanieMock */
        $beanieMock = $this
            ->getMockBuilder(Beanie::class)
            ->disableOriginalConstructor()
            ->setMethods(['workers'])
            ->getMock();

        $beanieMock
            ->expects($this->once())
            ->method('workers')
            ->willReturn([]);

        /** @var \PHPUnit_Framework_MockObject_MockObject|EventLoop $eventLoopMock */
        $eventLoopMock = $this
            ->getMockBuilder(EventLoop::class)
            ->disableOriginalConstructor()
            ->setMethods(['run'])
            ->getMock();

        $workerBuilder = new WorkerBuilder();
        $workerBuilder->withEventLoop($eventLoopMock);
        /** @var \PHPUnit_Framework_MockObject_MockObject|Worker $workerStub */
        $workerStub = $this->getMockBuilder(Worker::class)
            ->setMethods(['registerWatchers', 'shutdown'])
            ->setConstructorArgs($workerBuilder->getConstructorArguments($beanieMock))
            ->getMock();


        $workerStub->run();
        $this->assertTrue($workerStub->checkTimeToLive());
    }

    public function testCheckTimeToLive_returnsFalseWhenNotLivingTooLong()
    {
        $this->getNativeFunctionMock(['time'])
            ->expects($this->exactly(2))
            ->method('time')
            ->willReturnOnConsecutiveCalls(
                (\time() - QManConfig::DEFAULT_MAX_TIME_ALIVE + 1),
                (\time())
            );

        /** @var \PHPUnit_Framework_MockObject_MockObject|Beanie $beanieMock */
        $beanieMock = $this
            ->getMockBuilder(Beanie::class)
            ->disableOriginalConstructor()
            ->setMethods(['workers'])
            ->getMock();

        $beanieMock
            ->expects($this->once())
            ->method('workers')
            ->willReturn([]);

        /** @var \PHPUnit_Framework_MockObject_MockObject|EventLoop $eventLoopMock */
        $eventLoopMock = $this
            ->getMockBuilder(EventLoop::class)
            ->disableOriginalConstructor()
            ->setMethods(['run'])
            ->getMock();

        $workerBuilder = new WorkerBuilder();
        $workerBuilder->withEventLoop($eventLoopMock);
        /** @var \PHPUnit_Framework_MockObject_MockObject|Worker $workerStub */
        $workerStub = $this->getMockBuilder(Worker::class)
            ->setMethods(['registerWatchers', 'shutdown'])
            ->setConstructorArgs($workerBuilder->getConstructorArguments($beanieMock))
            ->getMock();


        $workerStub->run();
        $this->assertFalse($workerStub->checkTimeToLive());
    }

    public function testCheckMaximalMemoryUsage_returnsTrueWhenMemoryUsageExceedsLimits()
    {
        $this->getNativeFunctionMock(['memory_get_usage'])
            ->expects($this->once())
            ->method('memory_get_usage')
            ->willReturn(QManConfig::DEFAULT_MAX_MEMORY_USAGE + 1);

        /** @var \PHPUnit_Framework_MockObject_MockObject|Beanie $beanieMock */
        $beanieMock = $this
            ->getMockBuilder(Beanie::class)
            ->disableOriginalConstructor()
            ->getMock();


        $worker = (new WorkerBuilder())->build($beanieMock);


        $this->assertTrue($worker->checkMaximalMemoryUsage());
    }

    public function testCheckMaximalMemoryUsage_returnsFalseWhenMemoryUsageDoesNotExceedLimits()
    {
        $this->getNativeFunctionMock(['memory_get_usage'])
            ->expects($this->once())
            ->method('memory_get_usage')
            ->willReturn(QManConfig::DEFAULT_MAX_MEMORY_USAGE - 1);

        /** @var \PHPUnit_Framework_MockObject_MockObject|Beanie $beanieMock */
        $beanieMock = $this
            ->getMockBuilder(Beanie::class)
            ->disableOriginalConstructor()
            ->getMock();


        $worker = (new WorkerBuilder())->build($beanieMock);


        $this->assertFalse($worker->checkMaximalMemoryUsage());
    }

    public function testCreateJobFromBeanieJob_unserializesJobData_createsJob()
    {
        $testData = 'test';

        $commandMock = $this
            ->getMockBuilder(Command::class)
            ->getMockForAbstractClass();

        /** @var \PHPUnit_Framework_MockObject_MockObject|CommandSerializer $commandSerializerMock */
        $commandSerializerMock = $this
            ->getMockBuilder(CommandSerializer::class)
            ->setMethods(['unserialize'])
            ->getMockForAbstractClass();

        $commandSerializerMock
            ->expects($this->once())
            ->method('unserialize')
            ->with('test')
            ->willReturn($commandMock);

        /** @var \PHPUnit_Framework_MockObject_MockObject|\Beanie\Job\Job $beanieJobMock */
        $beanieJobMock = $this
            ->getMockBuilder(\Beanie\Job\Job::class)
            ->disableOriginalConstructor()
            ->setMethods(['getData'])
            ->getMock();

        $beanieJobMock
            ->expects($this->once())
            ->method('getData')
            ->willReturn($testData);

        /** @var \PHPUnit_Framework_MockObject_MockObject|Beanie $beanieMock */
        $beanieMock = $this
            ->getMockBuilder(Beanie::class)
            ->disableOriginalConstructor()
            ->getMock();


        $worker = (new WorkerBuilder())->withCommandSerializer($commandSerializerMock)->build($beanieMock);


        $job = $worker->createJobFromBeanieJob($beanieJobMock);


        $this->assertInstanceOf(Job::class, $job);
    }

    public function testHandleJob_deletesJobOnSuccess()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|Command $jobMock */
        $jobMock = $this
            ->getMockBuilder(Job::class)
            ->disableOriginalConstructor()
            ->setMethods(['execute', 'delete'])
            ->getMockForAbstractClass();

        /** @var \PHPUnit_Framework_MockObject_MockObject|Worker $workerStub */
        $workerStub = $this
            ->getMockBuilder(Worker::class)
            ->disableOriginalConstructor()
            ->setMethods(['createJobFromBeanieJob'])
            ->getMock();

        /** @var \PHPUnit_Framework_MockObject_MockObject|\Beanie\Job\Job $beanieJobMock */
        $beanieJobMock = $this
            ->getMockBuilder(\Beanie\Job\Job::class)
            ->disableOriginalConstructor()
            ->getMock();

        $workerStub
            ->expects($this->once())
            ->method('createJobFromBeanieJob')
            ->with($beanieJobMock)
            ->willReturn($jobMock);


        $jobMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $jobMock
            ->expects($this->once())
            ->method('delete');


        $workerStub->handleJob($beanieJobMock);
    }

    public function testHandleJob_executeReturnsFalse_delegatesToJobFailureStrategy()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|Command $jobMock */
        $jobMock = $this
            ->getMockBuilder(Job::class)
            ->disableOriginalConstructor()
            ->setMethods(['execute', 'delete'])
            ->getMockForAbstractClass();

        /** @var \PHPUnit_Framework_MockObject_MockObject|JobFailureStrategy $jobFailureStrategy */
        $jobFailureStrategy = $this
            ->getMockBuilder(JobFailureStrategy::class)
            ->setMethods(['handleFailedJob'])
            ->getMockForAbstractClass();

        $jobFailureStrategy
            ->expects($this->once())
            ->method('handleFailedJob')
            ->with($jobMock);

        /** @var \PHPUnit_Framework_MockObject_MockObject|Beanie $beanieMock */
        $beanieMock = $this->getMockBuilder(Beanie::class)->disableOriginalConstructor()->getMock();

        $constructorArguments = (new WorkerBuilder())
            ->withJobFailureStrategy($jobFailureStrategy)
            ->getConstructorArguments($beanieMock);

        /** @var \PHPUnit_Framework_MockObject_MockObject|Worker $workerStub */
        $workerStub = $this
            ->getMockBuilder(Worker::class)
            ->setConstructorArgs($constructorArguments)
            ->setMethods(['createJobFromBeanieJob'])
            ->getMock();

        /** @var \PHPUnit_Framework_MockObject_MockObject|\Beanie\Job\Job $beanieJobMock */
        $beanieJobMock = $this
            ->getMockBuilder(\Beanie\Job\Job::class)
            ->disableOriginalConstructor()
            ->getMock();

        $workerStub
            ->expects($this->once())
            ->method('createJobFromBeanieJob')
            ->with($beanieJobMock)
            ->willReturn($jobMock);


        $jobMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn(false);

        $jobMock
            ->expects($this->never())
            ->method('delete');


        $workerStub->handleJob($beanieJobMock);
    }

    public function testHandleJob_executeThrowsException_delegatesToJobFailureStrategy()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|Command $jobMock */
        $jobMock = $this
            ->getMockBuilder(Job::class)
            ->disableOriginalConstructor()
            ->setMethods(['execute', 'delete'])
            ->getMockForAbstractClass();

        /** @var \PHPUnit_Framework_MockObject_MockObject|JobFailureStrategy $jobFailureStrategy */
        $jobFailureStrategy = $this
            ->getMockBuilder(JobFailureStrategy::class)
            ->setMethods(['handleFailedJob'])
            ->getMockForAbstractClass();

        $jobFailureStrategy
            ->expects($this->once())
            ->method('handleFailedJob')
            ->with($jobMock);

        /** @var \PHPUnit_Framework_MockObject_MockObject|Beanie $beanieMock */
        $beanieMock = $this->getMockBuilder(Beanie::class)->disableOriginalConstructor()->getMock();

        $constructorArguments = (new WorkerBuilder())
            ->withJobFailureStrategy($jobFailureStrategy)
            ->getConstructorArguments($beanieMock);

        /** @var \PHPUnit_Framework_MockObject_MockObject|Worker $workerStub */
        $workerStub = $this
            ->getMockBuilder(Worker::class)
            ->setConstructorArgs($constructorArguments)
            ->setMethods(['createJobFromBeanieJob'])
            ->getMock();

        /** @var \PHPUnit_Framework_MockObject_MockObject|\Beanie\Job\Job $beanieJobMock */
        $beanieJobMock = $this
            ->getMockBuilder(\Beanie\Job\Job::class)
            ->disableOriginalConstructor()
            ->getMock();

        $workerStub
            ->expects($this->once())
            ->method('createJobFromBeanieJob')
            ->with($beanieJobMock)
            ->willReturn($jobMock);


        $jobMock
            ->expects($this->once())
            ->method('execute')
            ->willThrowException(new \RuntimeException());

        $jobMock
            ->expects($this->never())
            ->method('delete');


        $workerStub->handleJob($beanieJobMock);
    }
}
