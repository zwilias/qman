<?php


namespace QMan;

/**
 * Class WorkerConfigTest
 * @package QMan
 * @covers \QMan\QManConfig
 */
class QManConfigTest extends \PHPUnit_Framework_TestCase
{
    public function testConstruct_assignsDefaults()
    {
        $config = new QManConfig();


        $this->assertEquals(QManConfig::DEFAULT_MAX_MEMORY_USAGE, $config->getMaxMemoryUsage());
        $this->assertEquals(QManConfig::DEFAULT_MAX_TIME_ALIVE, $config->getMaxTimeAlive());
        $this->assertEquals([QManConfig::DEFAULT_TERMINATION_SIGNAL], $config->getTerminationSignals());
    }

    /**
     * @param string $property
     * @param mixed $value
     *
     * @dataProvider propertyValueProvider
     */
    public function testSetters_notLocked_setsValue($property, $value)
    {
        $config = new QManConfig();
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

        $config = new QManConfig();
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
            ['terminationSignals', [SIGUSR1]]
        ];
    }
}
