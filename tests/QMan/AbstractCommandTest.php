<?php


namespace QMan;


class AbstractCommandTest extends \PHPUnit_Framework_TestCase
{
    /** @var AbstractCommand */
    private $abstractCommand;

    public function setUp()
    {
        $this->abstractCommand = $this
            ->getMockBuilder(AbstractCommand::class)
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
}
