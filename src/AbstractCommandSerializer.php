<?php


namespace QMan;


abstract class AbstractCommandSerializer implements CommandSerializer
{
    /**
     * @param Command $command
     * @return string
     */
    public function serialize(Command $command)
    {
        return json_encode([
            'type' => $command->getType(),
            'data' => $command->getData()
        ]);
    }

    /**
     * @param string $data
     * @return Command
     */
    public function unserialize($data)
    {
        $info = json_decode($data, true);

        return $this->createCommand($info['type'], $info['data']);
    }

    /**
     * @param string $type
     * @param mixed $data
     * @return Command
     */
    abstract public function createCommand($type, $data);
}
