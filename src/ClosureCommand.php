<?php


namespace QMan;


use SuperClosure\Serializer;

class ClosureCommand implements Command
{
    /**
     * @var Serializer
     */
    protected $serializer;

    /**
     * @var \Closure
     */
    protected $closure;

    /**
     * @param Serializer|null $serializer
     */
    public function __construct(Serializer $serializer = null)
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
     * @return Command
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
