<?php
namespace QMan;

interface CommandSerializerInterface
{
    /**
     * @param CommandInterface $command
     * @return string
     */
    public function serialize(CommandInterface $command);

    /**
     * @param string $data
     * @return CommandInterface
     */
    public function unserialize($data);
}
