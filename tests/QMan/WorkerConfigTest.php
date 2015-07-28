<?php


namespace QMan;

/**
 * Class WorkerConfigTest
 * @package QMan
 * @covers \QMan\WorkerConfig
 */
class WorkerConfigTest extends \PHPUnit_Framework_TestCase
{
    public function testConstruct_assignsDefaults()
    {
        $config = new WorkerConfig();


        $this->assertEquals(WorkerConfig::DEFAULT_MAX_MEMORY_USAGE, $config->getMaxMemoryUsage());
        $this->assertEquals(WorkerConfig::DEFAULT_MAX_TIME_ALIVE, $config->getMaxTimeAlive());
        $this->assertEquals(WorkerConfig::DEFAULT_RESERVE_TIMEOUT, $config->getReserveTimeout());
        $this->assertEquals(WorkerConfig::DEFAULT_TERMINATION_SIGNAL, $config->getTerminationSignal());
    }

    /**
     * @param string $property
     * @param mixed $value
     *
     * @dataProvider propertyValueProvider
     */
    public function testSetters_notLocked_setsValue($property, $value)
    {
        $config = new WorkerConfig();
        $setter = 'set' . ucfirst($property);
        $getter = 'get' . ucfirst($property);


        $this->assertSame($config, $config->{$setter}($value));
        $this->assertEquals($value, $config->{$getter}());
    }

    /**
     * @param string $property
     * @param mixed $value
     * @dataProvider propertyValueProvider
     */
    public function testSetters_locked_nothingSet($property, $value)
    {
        $caughtException = false;

        $config = new WorkerConfig();
        $config->lock();

        $setter = 'set' . ucfirst($property);
        $getter = 'get' . ucfirst($property);

        try {
            $config->{$setter}($value);
        } catch (\BadMethodCallException $ex) {
            $caughtException = true;
            $this->assertNotEquals($value, $config->{$getter}());
        }

        if (!$caughtException) {
            $this->fail('Expected \BadMethodCallException to be thrown');
        }
    }

    public function propertyValueProvider()
    {
        return [
            ['maxMemoryUsage', 10],
            ['maxTimeAlive', 100],
            ['reserveTimeout', 20],
            ['terminationSignal', SIGUSR1]
        ];
    }
}
