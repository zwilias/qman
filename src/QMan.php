<?php


namespace QMan;


use Beanie\Beanie;
use Beanie\Exception\AbstractServerException;
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

    /** @var CommandSerializer */
    protected $serializer;

    /** @var bool */
    protected $fallbackEnabled = false;

    /**
     * @param Producer $producer
     * @param CommandSerializer $serializer
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        Producer $producer, CommandSerializer $serializer = null, LoggerInterface $logger = null
    ) {
        $this->producer = $producer;
        $this->logger = $logger ?: new NullLogger();
        $this->serializer = $serializer ?: new GenericCommandSerializer();
    }

    /**
     * @param Command $command
     * @param int $priority
     * @param int $delay
     * @param int $timeToRun
     */
    public function queue(
        Command $command,
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
        } catch (AbstractServerException $serverException) {
            $this->handlePutFailure($serverException, $command);
        }
    }

    /**
     * @param AbstractServerException $exception
     * @param Command $command
     * @throws AbstractServerException
     */
    protected function handlePutFailure(AbstractServerException $exception, Command $command)
    {
        $this->logger->alert('Failed to queue command due to server-exception', [
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
     * @return CommandSerializer
     */
    public function getSerializer()
    {
        return $this->serializer;
    }

    /**
     * @param string[] $servers
     * @param CommandSerializer $serializer
     * @param LoggerInterface $logger
     * @return static
     */
    public static function create(array $servers, CommandSerializer $serializer = null, LoggerInterface $logger = null)
    {
        return new static(
            Beanie::pool($servers)->producer(),
            $serializer,
            $logger
        );
    }
}
