<?php


namespace QMan;


interface ConfigAwareInterface
{
    /**
     * @param QManConfig $config
     * @return $this
     */
    public function setConfig(QManConfig $config);
}
