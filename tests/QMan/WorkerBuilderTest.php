<?php


namespace QMan;


use Beanie\Beanie;
use Psr\Log\NullLogger;

/**
 * Class WorkerBuilderTest
 * @package QMan
 * @covers \QMan\WorkerBuilder
 */
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
        $qManConfig = new QManConfig();


        $this->workerBuilder->withQManConfig($qManConfig);


        $this->assertContains($qManConfig, $this->workerBuilder->getConstructorArguments($this->getBeanieMock()));
    }

    public function testBuild_withLogger()
    {
        $logger = new NullLogger();


        $this->workerBuilder->withLogger($logger);


        $this->assertContains($logger, $this->workerBuilder->getConstructorArguments($this->getBeanieMock()));
    }

    public function testBuild_withEventLoop()
    {
        $eventLoop = new EventLoop();


        $this->workerBuilder->withEventLoop($eventLoop);


        $this->assertContains($eventLoop, $this->workerBuilder->getConstructorArguments($this->getBeanieMock()));
    }

    public function testBuild_withCommandSerializer()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|CommandSerializer $commandSerializerMock */
        $commandSerializerMock = $this
            ->getMockBuilder(AbstractCommandSerializer::class)
            ->getMockForAbstractClass();


        $this->workerBuilder->withCommandSerializer($commandSerializerMock);


        $this->assertContains(
            $commandSerializerMock,
            $this->workerBuilder->getConstructorArguments($this->getBeanieMock())
        );
    }

    public function testBuild_withJobFailureStrategy()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|JobFailureStrategy $jobFailureStrategy */
        $jobFailureStrategy = $this
            ->getMockBuilder(JobFailureStrategy::class)
            ->getMockForAbstractClass();


        $this->workerBuilder->withJobFailureStrategy($jobFailureStrategy);


        $this->assertContains(
            $jobFailureStrategy,
            $this->workerBuilder->getConstructorArguments($this->getBeanieMock())
        );
    }
}
