<?php


namespace QMan;


abstract class AbstractCommandSerializer implements CommandSerializerInterface
{
    /**
     * @param CommandInterface $command
     * @return string
     */
    public function serialize(CommandInterface $command)
    {
        return json_encode([
            'type' => $command->getType(),
            'data' => $command->getData()
        ]);
    }

    /**
     * @param string $data
     * @return CommandInterface
     */
    public function unserialize($data)
    {
        $info = json_decode($data, true);

        return $this->createCommand($info['type'], $info['data']);
    }

    /**
     * @param string $type
     * @param mixed $data
     * @return CommandInterface
     */
    abstract public function createCommand($type, $data);
}
