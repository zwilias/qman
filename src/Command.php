<?php


namespace QMan;


interface Command
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
     * @return Command
     */
    public function setData($data);

    /**
     * @return mixed
     */
    public function getData();
}
