<?php


namespace QMan;


abstract class AbstractCommand implements CommandInterface
{
    /** @var mixed */
    protected $data;

    /**
     * @param mixed $data
     * @return CommandInterface
     */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @return $this
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     * @return static
     */
    public static function create($data)
    {
        $command = new static();
        $command->setData($data);

        return $command;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'type' => $this->getType(),
            'data' => $this->getData()
        ];
    }


}
