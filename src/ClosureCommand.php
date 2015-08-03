<?php


namespace QMan;


use SuperClosure\Serializer;
use SuperClosure\SerializerInterface;

class ClosureCommand extends AbstractCommand
{
    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var \Closure
     */
    protected $closure;

    /**
     * @param SerializerInterface|null $serializer
     */
    public function __construct(SerializerInterface $serializer = null)
    {
        $this->serializer = $serializer ?: new Serializer();
    }

    /**
     * @return boolean
     */
    public function execute()
    {
        return call_user_func($this->closure);
    }

    /**
     * @return string
     */
    public function getType()
    {
        return GenericCommandSerializer::TYPE_CLOSURE;
    }

    /**
     * @param mixed $data
     * @return CommandInterface
     */
    public function setData($data)
    {
        $this->closure = $this->serializer->unserialize($data);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->serializer->serialize($this->closure);
    }

    /**
     * @param \Closure $closure
     * @return $this
     */
    public function setClosure(\Closure $closure)
    {
        $this->closure = $closure;
        return $this;
    }
}
