<?php


namespace QMan;

use Beanie\Job\Job;

$nativeFunctionMock = null;
$mockedNativeFunctions = [
    'time',
    'pcntl_signal',
    'pcntl_signal_dispatch',
    'memory_get_usage',
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

/**
 * Class ShutdownHandlerTest
 * @package QMan
 * @covers \QMan\ShutdownHandler
 */
class ShutdownHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor_locksConfig()
    {
        $configStub = $this
            ->getMockBuilder(WorkerConfig::class)
            ->setMethods(['lock'])
            ->getMock();

        $configStub
            ->expects($this->once())
            ->method('lock')
            ->willReturnSelf();


        new ShutdownHandler($configStub);
    }

    /**
     * @expectedException \QMan\ExitException
     */
    public function testShutdown_throwsExitException()
    {
        (new ShutdownHandler())->handleShutdown();
    }

    public function testStart_registersStartTime_registersSignalHandler_closesSignalWindow()
    {
        $handler = new ShutdownHandler();

        $this->getNativeFunctionMock()
            ->expects($this->once())
            ->method('time')
            ->willReturnCallback(function () { return \time(); });

        $this->getNativeFunctionMock()
            ->expects($this->once())
            ->method('pcntl_signal')
            ->with(
                WorkerConfig::DEFAULT_TERMINATION_SIGNAL,
                [$handler, 'handleShutDown'],
                false
            );

        $this->getNativeFunctionMock()
            ->expects($this->once())
            ->method('pcntl_sigprocmask')
            ->with(SIG_BLOCK, [WorkerConfig::DEFAULT_TERMINATION_SIGNAL]);

        $handler->start();
    }

    public function testPollSignals_opensWindow_checksSignals_closesWindow()
    {
        $this->getNativeFunctionMock()
            ->expects($this->exactly(2))
            ->method('pcntl_sigprocmask')
            ->withConsecutive(
                [SIG_UNBLOCK, [WorkerConfig::DEFAULT_TERMINATION_SIGNAL]],
                [SIG_BLOCK, [WorkerConfig::DEFAULT_TERMINATION_SIGNAL]]
            );

        $this->getNativeFunctionMock()
            ->expects($this->once())
            ->method('pcntl_signal_dispatch');


        (new ShutdownHandler())->pollSignals();
    }

    public function testPollSignals_memoryUsageExceeded_shutdown()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|ShutdownHandler $shutdownHandlerMock */
        $shutdownHandlerMock = $this
            ->getMockBuilder(ShutdownHandler::class)
            ->setMethods(['handleShutDown'])
            ->getMock();

        $shutdownHandlerMock
            ->expects($this->once())
            ->method('handleShutDown');


        $this->getNativeFunctionMock()
            ->expects($this->once())
            ->method('memory_get_usage')
            ->willReturn(WorkerConfig::DEFAULT_MAX_MEMORY_USAGE + 1);


        $shutdownHandlerMock->pollSignals();
    }

    public function testPollSignals_timeLimitExceeded_shutdown()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|ShutdownHandler $shutdownHandlerMock */
        $shutdownHandlerMock = $this
            ->getMockBuilder(ShutdownHandler::class)
            ->setMethods(['handleShutDown'])
            ->getMock();

        $shutdownHandlerMock
            ->expects($this->once())
            ->method('handleShutDown');


        $this->getNativeFunctionMock()
            ->expects($this->once())
            ->method('time')
            ->willReturn(WorkerConfig::DEFAULT_MAX_TIME_ALIVE + 1);


        $shutdownHandlerMock->pollSignals();
    }

    /**
     * @expectedException \QMan\ExitException
     */
    public function testPollSignals_withJob_shutdownReleasesJob()
    {
        $jobMock = $this
            ->getMockBuilder(Job::class)
            ->disableOriginalConstructor()
            ->setMethods(['getState', 'release'])
            ->getMock();

        $jobMock
            ->expects($this->once())
            ->method('getState')
            ->willReturn(Job::STATE_RESERVED);

        $jobMock
            ->expects($this->once())
            ->method('release')
            ->willReturnSelf();

        $this->getNativeFunctionMock()
            ->expects($this->once())
            ->method('memory_get_usage')
            ->willReturn(WorkerConfig::DEFAULT_MAX_MEMORY_USAGE + 1);


        (new ShutdownHandler())->pollSignals($jobMock);
    }

    public function tearDown()
    {
        global $nativeFunctionMock;
        $nativeFunctionMock = null;
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
