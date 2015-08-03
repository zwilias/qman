<?php


namespace QMan;


/**
 * Class GenericCommandSerializerTest
 * @package QMan
 * @covers \QMan\GenericCommandSerializer
 */
class GenericCommandSerializerTest extends \PHPUnit_Framework_TestCase
{
    /** @var GenericCommandSerializer */
    private $serializer;

    private $testType;
    private $testCommandClass;

    public function setUp()
    {
        $this->serializer = new GenericCommandSerializer();

        $this->testType = 'test-type';

        $this->testCommandClass = get_class(
            $this
                ->getMockBuilder(CommandInterface::class)
                ->setMethods(['setData'])
                ->getMockForAbstractClass()
        );

        $this->serializer->registerCommandType($this->testType, $this->testCommandClass);
    }

    public function testCreateCommand_returnsCommand()
    {
        $testCommand = $this->serializer->createCommand($this->testType, '');
        $this->assertInstanceOf($this->testCommandClass, $testCommand);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCreateCommand_unknownType_throwsException()
    {
        $this->serializer->createCommand('unknowntype', '');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage already mapped to Command class
     */
    public function testRegisterCommandType_existingType_throwsException()
    {
        $otherTestCommandClass = get_class(
            $this
                ->getMockBuilder(CommandInterface::class)
                ->setMethods(['setData', 'getData'])
                ->getMockForAbstractClass()
        );

        $this->serializer->registerCommandType($this->testType, $otherTestCommandClass);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage class already mapped as type
     */
    public function testRegisterCommandType_existingClass_throwsException()
    {
        $this->serializer->registerCommandType('othertesttype', $this->testCommandClass);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage class does not exist
     */
    public function testRegisterCommandType_classDoesNotExist_throwsException()
    {
        $this->serializer->registerCommandType('test', 'test_class');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage class does not implement
     */
    public function testRegisterCommandType_classExistsButDoesNotImplementCommand_throwsException()
    {
        $this->serializer->registerCommandType('someothertesttype', EventLoop::class);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage not a string
     */
    public function testRegisterCommandType_typeNotAString_throwsException()
    {
        $this->serializer->registerCommandType(true, $this->testCommandClass);
    }

    public function testRegisterCommandTypes_registersArrayOfTypes()
    {
        $testType ='customtype';
        $testCommandClass = get_class(
            $this
                ->getMockBuilder(CommandInterface::class)
                ->setMethods(['setData', 'getType'])
                ->getMockForAbstractClass()
        );


        $this->serializer->registerCommandTypes([
            $testType => $testCommandClass
        ]);


        $testCommand = $this->serializer->createCommand($testType, '');
        $this->assertInstanceOf($testCommandClass, $testCommand);
    }
}
