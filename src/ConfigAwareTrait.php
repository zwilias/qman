<?php


namespace QMan;


trait ConfigAwareTrait
{
    protected $config;

    public function setConfig(QManConfig $config)
    {
        $this->config = $config;
        $config->lock();
    }
}
