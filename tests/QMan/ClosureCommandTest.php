<?php


namespace QMan;

use SuperClosure\Serializer;
use SuperClosure\SerializerInterface;

/**
 * Class ClosureCommandTest
 * @package QMan
 * @covers \QMan\ClosureCommand
 */
class ClosureCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testExecute_executesClosure()
    {
        $executed = false;
        $closure = function () use (&$executed) {
            $executed = true;
        };

        $command = new ClosureCommand();
        $command->setClosure($closure);


        $command->execute();


        $this->assertTrue($executed, 'Closure should be executed');
    }

    public function testSetData_unserializedClosure()
    {
        $testData = '123';

        /** @var \PHPUnit_Framework_MockObject_MockObject|SerializerInterface $serializerMock */
        $serializerMock = $this
            ->getMockBuilder(SerializerInterface::class)
            ->setMethods(['unserialize'])
            ->getMockForAbstractClass();

        $serializerMock
            ->expects($this->once())
            ->method('unserialize')
            ->with($testData)
            ->willReturn(true);

        $command = new ClosureCommand($serializerMock);


        $command->setData($testData);
    }

    public function testGetData_serializesClosure()
    {
        $testClosure = function () {
            $this->fail('should never be executed');
        };

        $expectedData = (new Serializer())->serialize($testClosure);


        $command = new ClosureCommand();
        $command->setClosure($testClosure);
        $data = $command->getData();


        $this->assertEquals($expectedData, $data);
    }

    public function testGetType_returnsExpectedType()
    {
        $expectedType = GenericCommandSerializer::TYPE_CLOSURE;


        $this->assertEquals($expectedType, (new ClosureCommand())->getType());
    }
}
