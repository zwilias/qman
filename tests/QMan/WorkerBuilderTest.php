<?php


namespace QMan;


use Beanie\Beanie;
use Psr\Log\NullLogger;

class WorkerBuilderTest extends \PHPUnit_Framework_TestCase
{
    /** @var WorkerBuilder */
    private $workerBuilder;

    public function setUp()
    {
        $this->workerBuilder = new WorkerBuilder();
    }

    /**
     * @param array $methods
     * @return \PHPUnit_Framework_MockObject_MockObject|Beanie
     */
    private function getBeanieMock(array $methods = [])
    {
        return $this
            ->getMockBuilder(Beanie::class)
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();
    }

    public function testBuild_defaults()
    {
        $worker = $this->workerBuilder->build($this->getBeanieMock());


        $this->assertInstanceOf(Worker::class, $worker);
    }

    public function testBuild_withWorkerConfig()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|WorkerConfig $workerConfigMock */
        $workerConfigMock = $this
            ->getMockBuilder(WorkerConfig::class)
            ->setMethods(['lock'])
            ->getMock();

        $workerConfigMock
            ->expects($this->once())
            ->method('lock');


        $worker = $this->workerBuilder->withWorkerConfig($workerConfigMock)->build($this->getBeanieMock());


        $this->assertInstanceOf(Worker::class, $worker);
    }

    public function testBuild_withLogger()
    {
        $worker = $this->workerBuilder->withLogger(new NullLogger())->build($this->getBeanieMock());


        $this->assertInstanceOf(Worker::class, $worker);
    }

    public function testBuild_withEventLoop()
    {
        $worker = $this->workerBuilder->withEventLoop(new EventLoop())->build($this->getBeanieMock());


        $this->assertInstanceOf(Worker::class, $worker);
    }
}
