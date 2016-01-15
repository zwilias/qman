<?php


namespace QMan;


class AbstractCommandTest extends \PHPUnit_Framework_TestCase
{
    /** @var AbstractCommand|\PHPUnit_Framework_MockObject_MockObject */
    private $abstractCommand;

    public function setUp()
    {
        $this->abstractCommand = $this
            ->getMockBuilder(AbstractCommand::class)
            ->setMethods(['getType'])
            ->getMockForAbstractClass();
    }

    public function testHoldsData()
    {
        $testData = 'whatever';


        $this->assertSame($this->abstractCommand, $this->abstractCommand->setData($testData));
        $this->assertEquals($testData, $this->abstractCommand->getData());
    }

    public function testCreate_createsNewInstance()
    {
        $testData = 'something something 123';


        $command = call_user_func([get_class($this->abstractCommand), 'create'], $testData);


        $this->assertInstanceOf(AbstractCommand::class, $command);
        $this->assertEquals($testData, $command->getData());
    }

    public function testJsonEncode_EncodesData()
    {
        $testData = 'something something 123';
        $testType = 'testType';

        $expected = json_encode([
            'type' => $testType,
            'data' => $testData
        ]);

        $this->abstractCommand
            ->expects($this->once())
            ->method('getType')
            ->willReturn('testType');

        $this->abstractCommand->setData($testData);


        $this->assertJsonStringEqualsJsonString($expected, json_encode($this->abstractCommand));
    }
}
