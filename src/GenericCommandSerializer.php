<?php


namespace QMan;


class GenericCommandSerializer extends AbstractCommandSerializer
{
    const TYPE_CLOSURE = 'qman.closure';

    const MESSAGE_NOT_A_STRING = 'Failed to register Command class \'%2$s\': type \'%1$s\' is not a string';
    const MESSAGE_NO_SUCH_CLASS = 'Failed to register Command class \'%2$s\': class does not exist';
    const MESSAGE_NOT_A_COMMAND = 'Failed to register Command class \'%2$s\': class does not implement QMan\Command';
    const MESSAGE_CLASS_ALREADY_MAPPED = 'Failed to register Command class \'%2$s\': class already mapped as type \'%3$s\'';
    const MESSAGE_TYPE_ALREADY_MAPPED = 'Failed to register Command class \'%2$s\': type \'%1$s\' already mapped to Command class \'%3$s\'';

    /**
     * @var array<string,string>
     */
    protected $typeMap = [
        self::TYPE_CLOSURE => ClosureCommand::class
    ];

    /**
     * @param string $type
     * @param mixed $data
     * @return CommandInterface
     */
    public function createCommand($type, $data)
    {
        if (!isset($this->typeMap[$type])) {
            throw new \InvalidArgumentException(
                sprintf('Could not find Command-class for type \'%s\'', $type)
            );
        }

        /** @var CommandInterface $command */
        $command = new $this->typeMap[$type]();
        $command->setData($data);

        return $command;
    }

    /**
     * @param string $type
     * @param string $commandClass
     * @param bool $force Bypass all consistency checks
     * @return $this
     */
    public function registerCommandType($type, $commandClass, $force = false)
    {
        if ($force !== true) {
            $this->checkCommandType($type, $commandClass);
        }

        $this->typeMap[$type] = $commandClass;

        return $this;
    }

    /**
     * @param array $typeList
     */
    public function registerCommandTypes(array $typeList)
    {
        foreach ($typeList as $type => $commandClass) {
            $this->registerCommandType($type, $commandClass);
        }
    }

    /**
     * @param string $type
     * @param string $commandClass
     */
    protected function checkCommandType($type, $commandClass)
    {
        if (!is_string($type)) {
            throw $this->getTypeMapException(self::MESSAGE_NOT_A_STRING, $type, $commandClass);
        }

        if (!class_exists($commandClass)) {
            throw $this->getTypeMapException(self::MESSAGE_NO_SUCH_CLASS, $type, $commandClass);
        }

        if (!in_array(CommandInterface::class, class_implements($commandClass))) {
            throw $this->getTypeMapException(self::MESSAGE_NOT_A_COMMAND, $type, $commandClass);
        }

        if (($existingType = array_search($commandClass, $this->typeMap)) !== false) {
            throw $this->getTypeMapException(self::MESSAGE_CLASS_ALREADY_MAPPED, $type, $commandClass, $existingType);
        }

        if (isset($this->typeMap[$type])) {
            throw $this->getTypeMapException(
                self::MESSAGE_TYPE_ALREADY_MAPPED, $type, $commandClass, $this->typeMap[$type]
            );
        }
    }

    /**
     * @param string $message
     * @param string $type
     * @param string $commandClass
     * @param string|null $extra
     * @return \InvalidArgumentException
     */
    protected function getTypeMapException($message, $type, $commandClass, $extra = null)
    {
        return new \InvalidArgumentException(
            sprintf($message, $type, $commandClass, $extra)
        );
    }
}
