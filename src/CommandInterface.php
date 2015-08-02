<?php


namespace QMan;


interface CommandInterface
{
    /**
     * @return boolean
     */
    public function execute();

    /**
     * @return string
     */
    public function getType();

    /**
     * @param mixed $data
     * @return CommandInterface
     */
    public function setData($data);

    /**
     * @return mixed
     */
    public function getData();
}
