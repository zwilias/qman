<?php


namespace QMan;

require_once 'NativeFunctionStub_TestCase.php';

use Psr\Log\LoggerInterface;

class ErrorHandlerTest extends NativeFunctionStub_TestCase
{
    /**
     * @var ErrorHandler
     */
    protected $errorHandler;

    public function setUp()
    {
        $this->errorHandler = new ErrorHandler();
    }

    /**
     * @param array $error
     * @dataProvider getNonLoggedErrors
     */
    public function testHandleShutdown_nonFatalErrorDoesNothing($error)
    {
        $nativeFunctions = $this->getNativeFunctionMock(['error_get_last']);
        $nativeFunctions
            ->expects($this->once())
            ->method('error_get_last')
            ->willReturn($error);

        $loggerMock = $this->getLoggerMock();
        $loggerMock
            ->expects($this->never())
            ->method('critical');

        $workerMock = $this->getWorkerMock();
        $workerMock
            ->expects($this->never())
            ->method('stop');

        $this->errorHandler->handleShutdown($workerMock, $loggerMock);
    }

    /**
     * @param array $error
     * @dataProvider getFatalErrors
     */
    public function testHandleShutdown_fatalError_logsErrorStopsWorker(array $error)
    {
        $nativeFunctions = $this->getNativeFunctionMock(['error_get_last']);
        $nativeFunctions
            ->expects($this->once())
            ->method('error_get_last')
            ->willReturn($error);

        $loggerMock = $this->getLoggerMock();
        $loggerMock
            ->expects($this->once())
            ->method('critical')
            ->with(
                $this->isType('string'),
                $this->equalTo($error)
            );

        $workerMock = $this->getWorkerMock();
        $workerMock
            ->expects($this->once())
            ->method('stop');

        $this->errorHandler->handleShutdown($workerMock, $loggerMock);
    }

    /**
     * @return array
     */
    public function getFatalErrors()
    {
        return [
            [['type' => E_ERROR]],
            [['type' => E_USER_ERROR]],
            [['type' => E_PARSE]],
            [['type' => E_CORE_ERROR]],
            [['type' => E_COMPILE_ERROR]],
            [['type' => E_RECOVERABLE_ERROR]]
        ];
    }

    /**
     * @return array
     */
    public function getNonLoggedErrors()
    {
        return [
            [null],
            [['type' => E_NOTICE]],
            [['type' => E_WARNING]],
            [['type' => E_USER_NOTICE]],
            [['type' => E_USER_WARNING]],
            [['type' => E_USER_DEPRECATED]],
            [['type' => E_DEPRECATED]],
            [['type' => E_STRICT]],
            [['type' => E_CORE_WARNING]],
            [['type' => E_COMPILE_WARNING]]
        ];
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|LoggerInterface
     */
    protected function getLoggerMock()
    {
        return $this
            ->getMockBuilder(LoggerInterface::class)
            ->setMethods(['critical'])
            ->getMockForAbstractClass();
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|Worker
     */
    protected function getWorkerMock()
    {
        return $this
            ->getMockBuilder(Worker::class)
            ->disableOriginalConstructor()
            ->setMethods(['stop'])
            ->getMock();
    }
}
