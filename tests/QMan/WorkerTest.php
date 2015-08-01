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

    /**
     * @expectedException \Exception
     */
    public function testConstruct_evExtensionNotLoaded_throwsException()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|Beanie $beanieMock */
        $beanieMock = $this
            ->getMockBuilder(Beanie::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->getNativeFunctionMock(['extension_loaded'])
            ->expects($this->once())
            ->method('extension_loaded')
            ->willReturn(false);


        new Worker($beanieMock);
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
}
