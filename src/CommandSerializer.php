<?php
namespace QMan;

interface CommandSerializer
{
    /**
     * @param Command $command
     * @return string
     */
    public function serialize(Command $command);

    /**
     * @param string $data
     * @return Command
     */
    public function unserialize($data);
}
