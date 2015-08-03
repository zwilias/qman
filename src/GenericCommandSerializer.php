<?php


namespace QMan;


class GenericCommandSerializer extends AbstractCommandSerializer
{
    const TYPE_CLOSURE = 'qman.closure';

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
     * @param bool $force   Bypass all consistency checks
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
            throw new \InvalidArgumentException(
                sprintf('Failed to register Command class \'%s\': type \'%s\' is not a string', $commandClass, $type)
            );
        }

        if (!class_exists($commandClass)) {
            throw new \InvalidArgumentException(
                sprintf('Failed to register Command class \'%s\': class does not exist', $commandClass)
            );
        }

        if (!in_array(CommandInterface::class, class_implements($commandClass))) {
            throw new \InvalidArgumentException(
                sprintf('Failed to register Command class \'%s\': class does not implement QMan\Command', $commandClass)
            );
        }

        if (($existingType = array_search($commandClass, $this->typeMap)) !== false) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Failed to register Command class \'%s\': class already mapped as type \'%s\'',
                    $commandClass,
                    $existingType
                )
            );
        }

        if (isset($this->typeMap[$type])) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Failed to register Command class \'%s\': type \'%s\' already mapped to Command class \'%s\'',
                    $commandClass,
                    $type,
                    $this->typeMap[$type]
                )
            );
        }
    }
}
