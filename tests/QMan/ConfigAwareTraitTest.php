<?php


namespace QMan;

/**
 * Class ConfigAwareTraitTest
 * @package QMan
 * @covers \QMan\ConfigAwareTrait
 */
class ConfigAwareTraitTest extends \PHPUnit_Framework_TestCase
{
    public function testSetConfig_setsConfig()
    {
        $stub = new ConfigAwareStub();
        $config = new QManConfig();


        $stub->setConfig($config);


        $this->assertSame($config, $stub->getConfig());
    }
}

class ConfigAwareStub
{
    use ConfigAwareTrait;

    public function getConfig()
    {
        return $this->config;
    }
}
