<?php


namespace QMan;


class GenericJobFailureStrategyTest extends \PHPUnit_Framework_TestCase
{
    /** @var GenericJobFailureStrategy */
    private $strategy;

    /** @var \PHPUnit_Framework_MockObject_MockObject|Job */
    private $job;

    public function setUp()
    {
        $this->strategy = new GenericJobFailureStrategy(new QManConfig());
        $this->job = $this
            ->getMockBuilder(Job::class)
            ->setMethods(['stats', 'bury', 'release'])
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @param array $stats
     * @param string $expectedMethod
     * @param array $expectedParams
     * @dataProvider failedJobDataProvider
     */
    public function testHandleFailedJob(array $stats, $expectedMethod, array $expectedParams)
    {
        $this->job
            ->expects($this->once())
            ->method('stats')
            ->willReturn($stats);

        $invocationMocker = $this->job
            ->expects($this->once())
            ->method($expectedMethod);

        call_user_func_array([$invocationMocker, 'with'], $expectedParams);


        $this->strategy->handleFailedJob($this->job);
    }

    public function failedJobDataProvider()
    {
        return [
            'first-failure' => [
                'stats' => ['reserves' => 1, 'pri' => 10],
                'command' => 'release',
                'params' => [10, QManConfig::DEFAULT_FAILURE_DELAY]
            ],
            'second-failure' => [
                'stats' => ['reserves' => 2, 'pri' => 50],
                'command' => 'release',
                'params' => [50, QManConfig::DEFAULT_FAILURE_DELAY * 2]
            ],
            'third-failure' => [
                'stats' => ['reserves' => 3, 'pri' => 1220],
                'command' => 'bury',
                'params' => [1220]
            ],
            'fourth-failure' => [
                'stats' => ['reserves' => 4, 'pri' => 444],
                'command' => 'release',
                'params' => [444, QManConfig::DEFAULT_FAILURE_DELAY]
            ]
        ];
    }
}
