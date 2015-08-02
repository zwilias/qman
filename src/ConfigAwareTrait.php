<?php


namespace QMan;


trait ConfigAwareTrait
{
    /** @var QManConfig */
    protected $config;

    /**
     * @param QManConfig $config
     */
    public function setConfig(QManConfig $config)
    {
        $this->config = $config;
        $config->lock();
    }
}
