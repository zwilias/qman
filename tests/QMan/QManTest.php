<?php


namespace QMan;


use Beanie\Exception\AbstractServerException;
use Beanie\Producer;

/**
 * Class QManTest
 * @package QMan
 * @covers \QMan\QMan
 */
class QManTest extends \PHPUnit_Framework_TestCase
{
    const TEST_TUBE = 'test';

    /** @var \PHPUnit_Framework_MockObject_MockObject|Producer */
    protected $producerMock;

    /** @var \PHPUnit_Framework_MockObject_MockObject|CommandSerializerInterface */
    protected $serializerMock;

    /** @var QMan */
    protected $qMan;

    /** @var \PHPUnit_Framework_MockObject_MockObject|CommandInterface */
    protected $commandMock;

    public function setUp()
    {
        $this->producerMock = $this
            ->getMockBuilder(Producer::class)
            ->disableOriginalConstructor()
            ->setMethods(['put', 'useTube'])
            ->getMock();

        $this->serializerMock = $this
            ->getMockBuilder(CommandSerializerInterface::class)
            ->setMethods(['serialize'])
            ->getMockForAbstractClass();

        $this->commandMock = $this
            ->getMockBuilder(CommandInterface::class)
            ->setMethods(['getType', 'getData', 'execute'])
            ->getMockForAbstractClass();

        $this->producerMock
            ->expects($this->any())
            ->method('useTube')
            ->with($this->isType('string'))
            ->willReturnSelf();

        $this->qMan = new QMan($this->producerMock, $this->serializerMock);
    }

    public function testGetSerializer_returnsCurrentSerializer()
    {
        $this->assertSame($this->serializerMock, $this->qMan->getSerializer());
    }

    /**
     * @param array $params
     * @dataProvider queueParamsProvider
     */
    public function testQueue_queues(array $params)
    {
        $testData = 'test';

        $this->serializerMock
            ->expects($this->once())
            ->method('serialize')
            ->with($this->commandMock)
            ->willReturn($testData);

        $invocationMocker = $this->producerMock
            ->expects($this->once())
            ->method('put');

        call_user_func_array([$invocationMocker, 'with'], array_merge([$testData], $params));


        call_user_func_array([$this->qMan, 'queue'], array_merge([$this->commandMock, self::TEST_TUBE], $params));
    }

    /**
     * @expectedException \Beanie\Exception\AbstractServerException
     * @dataProvider queueParamsProvider
     * @param array $params
     */
    public function testQueue_noFallback_exceptionThrown(array $params)
    {
        $testData = 'test';

        $this->serializerMock
            ->expects($this->once())
            ->method('serialize')
            ->with($this->commandMock)
            ->willReturn($testData);

        $invocationMocker = $this->producerMock
            ->expects($this->once())
            ->method('put');

        $serverExceptionMock = $this
            ->getMockBuilder(AbstractServerException::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $invocationMocker = call_user_func_array([$invocationMocker, 'with'], array_merge([$testData], $params));
        $invocationMocker->willThrowException($serverExceptionMock);


        call_user_func_array([$this->qMan, 'queue'], array_merge([$this->commandMock, self::TEST_TUBE], $params));
    }


    /**
     * @expectedException \RuntimeException
     * @dataProvider queueParamsProvider
     * @param array $params
     */
    public function testQueue_fallbackEnabled_exceptionThrown(array $params)
    {
        $testData = 'test';

        $this->qMan->enableFallback();

        $this->serializerMock
            ->expects($this->once())
            ->method('serialize')
            ->with($this->commandMock)
            ->willReturn($testData);


        $invocationMocker = $this->producerMock
            ->expects($this->once())
            ->method('put');

        $invocationMocker = call_user_func_array([$invocationMocker, 'with'], array_merge([$testData], $params));
        $invocationMocker->willThrowException(new \RuntimeException());


        call_user_func_array([$this->qMan, 'queue'], array_merge([$this->commandMock, self::TEST_TUBE], $params));
    }

    /**
     * @dataProvider queueParamsProvider
     * @param array $params
     */
    public function testQueue_fallbackEnabled_serverExceptionMeansTryAgain(array $params)
    {
        $testData = 'test';

        $this->qMan->enableFallback();

        $this->commandMock
            ->expects($this->once())
            ->method('execute');

        $this->serializerMock
            ->expects($this->once())
            ->method('serialize')
            ->with($this->commandMock)
            ->willReturn($testData);

        $serverExceptionMock = $this
            ->getMockBuilder(AbstractServerException::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $invocationMocker = $this->producerMock
            ->expects($this->once())
            ->method('put');

        $invocationMocker = call_user_func_array([$invocationMocker, 'with'], array_merge([$testData], $params));
        $invocationMocker->willThrowException($serverExceptionMock);


        call_user_func_array([$this->qMan, 'queue'], array_merge([$this->commandMock, self::TEST_TUBE], $params));
    }

    /**
     * @param array $params
     * @dataProvider queueParamsProvider
     */
    public function testQueueClosure_queues(array $params)
    {
        $closure = function () {
            $this->fail('Should not be executed');
        };

        $testData = 'test';

        $this->serializerMock
            ->expects($this->once())
            ->method('serialize')
            ->with($this->isInstanceOf(ClosureCommand::class))
            ->willReturn($testData);

        $invocationMocker = $this->producerMock
            ->expects($this->once())
            ->method('put');

        call_user_func_array([$invocationMocker, 'with'], array_merge([$testData], $params));


        call_user_func_array([$this->qMan, 'queueClosure'], array_merge([$closure, self::TEST_TUBE], $params));
    }

    public function testStaticCreate_createsFromListOfServerNames()
    {
        $qMan = QMan::create(['localhost:11300', 'localhost:11301']);


        $this->assertInstanceOf(QMan::class, $qMan);
    }

    public function queueParamsProvider()
    {
        return [
            [[]],
            [[123]],
            [[321, 44]],
            [[333, 31, 523]]
        ];
    }
}
