<?php


namespace QMan;


/**
 * Class AbstractCommandSerializerTest
 * @package QMan
 * @covers \QMan\AbstractCommandSerializer
 */
class AbstractCommandSerializerTest extends \PHPUnit_Framework_TestCase
{
    /** @var \PHPUnit_Framework_MockObject_MockObject|AbstractCommandSerializer */
    private $serializer;

    public function setUp()
    {
        $this->serializer = $this
            ->getMockBuilder(AbstractCommandSerializer::class)
            ->setMethods(['createCommand'])
            ->getMockForAbstractClass();
    }

    public function testSerialize_createsJSONSerializedArray()
    {
        $testType = 'test';
        $testData = 'testdata';

        $expected = json_encode([
            'type' => $testType,
            'data' => $testData
        ]);

        /** @var \PHPUnit_Framework_MockObject_MockObject|CommandInterface $commandMock */
        $commandMock = $this
            ->getMockBuilder(CommandInterface::class)
            ->setMethods(['getType', 'getData'])
            ->getMockForAbstractClass();

        $commandMock
            ->expects($this->once())
            ->method('getType')
            ->willReturn($testType);

        $commandMock
            ->expects($this->once())
            ->method('getData')
            ->willReturn($testData);


        $this->assertEquals($expected, $this->serializer->serialize($commandMock));
    }

    public function testUnserialize_createsCommandWithTypeAndData()
    {
        $expectedType = 'test';
        $expectedData = 'testdata';

        $testData = json_encode([
            'type' => $expectedType,
            'data' => $expectedData
        ]);

        $this->serializer
            ->expects($this->once())
            ->method('createCommand')
            ->with($expectedType, $expectedData);


        $this->serializer->unserialize($testData);
    }
}
