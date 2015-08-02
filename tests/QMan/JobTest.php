<?php


namespace QMan;


use Beanie\Beanie;

class JobTest extends \PHPUnit_Framework_TestCase
{
    /** @var Job */
    private $job;

    /** @var \PHPUnit_Framework_MockObject_MockObject|\Beanie\Job\Job */
    private $beanieJobMock;

    /** @var \PHPUnit_Framework_MockObject_MockObject|CommandInterface */
    private $commandMock;

    public function setUp()
    {
        $this->beanieJobMock = $this
            ->getMockBuilder(\Beanie\Job\Job::class)
            ->setMethods(['stats', 'release', 'bury', 'delete'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->commandMock = $this
            ->getMockBuilder(CommandInterface::class)
            ->setMethods(['execute'])
            ->getMockForAbstractClass();

        $this->job = new Job($this->beanieJobMock, $this->commandMock);
    }

    public function testStats_delegatesToBeanieJob()
    {
        $testStats = ['test' => 'stats'];

        $this->beanieJobMock
            ->expects($this->once())
            ->method('stats')
            ->willReturn($testStats);


        $this->assertSame($testStats, $this->job->stats());
    }

    public function testBury_delegatesToBeanieJob()
    {
        $this->beanieJobMock
            ->expects($this->once())
            ->method('bury')
            ->with(Beanie::DEFAULT_PRIORITY);


        $this->job->bury();
    }

    public function testBury_withParams_delegatesToBeanieJob()
    {
        $testPriority = 666;

        $this->beanieJobMock
            ->expects($this->once())
            ->method('bury')
            ->with($testPriority);


        $this->job->bury($testPriority);
    }

    public function testRelease_delegatesToBeanieJob()
    {
        $this->beanieJobMock
            ->expects($this->once())
            ->method('release')
            ->with(Beanie::DEFAULT_PRIORITY, Beanie::DEFAULT_DELAY);


        $this->job->release();
    }

    public function testRelease_withPriority_delegatesToBeanieJob()
    {
        $testPriority = 678;

        $this->beanieJobMock
            ->expects($this->once())
            ->method('release')
            ->with($testPriority, Beanie::DEFAULT_DELAY);


        $this->job->release($testPriority);
    }

    public function testRelease_withParams_delegatesToBeanieJob()
    {
        $testPriority = 1293;
        $testDelay = 1235;

        $this->beanieJobMock
            ->expects($this->once())
            ->method('release')
            ->with($testPriority, $testDelay);


        $this->job->release($testPriority, $testDelay);
    }

    public function testDelete_delegateToBeanieJob()
    {
        $this->beanieJobMock
            ->expects($this->once())
            ->method('delete');


        $this->job->delete();
    }

    public function testExecute_delegatesToCommand()
    {
        $this->commandMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);


        $this->assertTrue($this->job->execute());
    }
}
