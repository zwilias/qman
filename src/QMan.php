<?php


namespace QMan;


use Beanie\Beanie;
use Beanie\Exception\Exception;
use Beanie\Producer;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class QMan implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var Producer */
    protected $producer;

    /** @var CommandSerializerInterface */
    protected $serializer;

    /** @var bool */
    protected $fallbackEnabled = false;

    /**
     * @param Producer $producer
     * @param CommandSerializerInterface $serializer
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        Producer $producer, CommandSerializerInterface $serializer = null, LoggerInterface $logger = null
    ) {
        $this->producer = $producer;
        $this->logger = $logger ?: new NullLogger();
        $this->serializer = $serializer ?: new GenericCommandSerializer();
    }

    /**
     * @param CommandInterface $command
     * @param int $priority
     * @param int $delay
     * @param int $timeToRun
     */
    public function queue(
        CommandInterface $command,
        $priority = Beanie::DEFAULT_PRIORITY,
        $delay = Beanie::DEFAULT_DELAY,
        $timeToRun = Beanie::DEFAULT_TIME_TO_RUN
    ) {
        try {
            $this->producer->put(
                $this->serializer->serialize($command),
                $priority,
                $delay,
                $timeToRun
            );
        } catch (Exception $exception) {
            $this->handlePutFailure($exception, $command);
        }
    }

    /**
     * @param Exception $exception
     * @param CommandInterface $command
     * @throws AbstractServerException
     */
    protected function handlePutFailure(Exception $exception, CommandInterface $command)
    {
        $this->logger->alert('Failed to queue command due to unexpected exception', [
            'exception' => $exception,
            'producer' => $this->producer,
            'command' => $command
        ]);

        if ($this->fallbackEnabled === true) {
            $command->execute();
        } else {
            throw $exception;
        }
    }

    public function enableFallback()
    {
        $this->fallbackEnabled = true;
    }

    public function queueClosure(
        \Closure $closure,
        $priority = Beanie::DEFAULT_PRIORITY,
        $delay = Beanie::DEFAULT_DELAY,
        $timeToRun = Beanie::DEFAULT_TIME_TO_RUN
    ) {
        $closureCommand = new ClosureCommand();
        $closureCommand->setClosure($closure);

        $this->queue($closureCommand, $priority, $delay, $timeToRun);
    }

    /**
     * @return CommandSerializerInterface
     */
    public function getSerializer()
    {
        return $this->serializer;
    }

    /**
     * @param string[] $servers
     * @param CommandSerializerInterface $serializer
     * @param LoggerInterface $logger
     * @return static
     */
    public static function create(array $servers, CommandSerializerInterface $serializer = null, LoggerInterface $logger = null)
    {
        return new static(
            Beanie::pool($servers)->producer(),
            $serializer,
            $logger
        );
    }
}
