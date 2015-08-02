<?php


namespace QMan;


use Beanie\Beanie;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class WorkerBuilder
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var EventLoop
     */
    protected $eventLoop;

    /**
     * @var QManConfig
     */
    protected $qManConfig;

    /**
     * @var CommandSerializer
     */
    protected $commandSerializer;

    /**
     * @var JobFailureStrategy
     */
    protected $jobFailureStrategy;

    public function __construct()
    {
        $this->logger = new NullLogger();
        $this->qManConfig = new QManConfig();
        $this->eventLoop = new EventLoop($this->logger);
        $this->commandSerializer = new GenericCommandSerializer();
        $this->jobFailureStrategy = new GenericJobFailureStrategy($this->qManConfig, $this->logger);
    }

    /**
     * @param LoggerInterface $logger
     * @return $this
     */
    public function withLogger(LoggerInterface $logger)
    {
        $this->eventLoop->setLogger($logger);
        $this->jobFailureStrategy->setLogger($logger);
        $this->logger = $logger;
        return $this;
    }

    /**
     * @param QManConfig $config
     * @return $this
     */
    public function withQManConfig(QManConfig $config)
    {
        $this->jobFailureStrategy->setConfig($config);
        $this->qManConfig = $config;
        return $this;
    }

    /**
     * @param EventLoop $eventLoop
     * @return $this
     */
    public function withEventLoop(EventLoop $eventLoop)
    {
        $eventLoop->setLogger($this->logger);
        $this->eventLoop = $eventLoop;
        return $this;
    }

    /**
     * @param CommandSerializer $commandSerializer
     * @return $this
     */
    public function withCommandSerializer(CommandSerializer $commandSerializer)
    {
        $this->commandSerializer = $commandSerializer;
        return $this;
    }

    /**
     * @param JobFailureStrategy $strategy
     * @return $this
     */
    public function withJobFailureStrategy(JobFailureStrategy $strategy)
    {
        $strategy->setLogger($this->logger);
        $strategy->setConfig($this->qManConfig);
        $this->jobFailureStrategy = $strategy;
        return $this;
    }

    public function getConstructorArguments(Beanie $beanie)
    {
        return [
            $beanie,
            $this->qManConfig,
            $this->eventLoop,
            $this->commandSerializer,
            $this->jobFailureStrategy,
            $this->logger
        ];
    }

    /**
     * @param Beanie $beanie
     * @return Worker
     */
    public function build(Beanie $beanie)
    {
        // For now, do it this way. As soon as PHP55 is out of EOL, switch to the splat operator:
        // return new Worker(...$this->getConstructorArguments($beanie));
        return (new \ReflectionClass(Worker::class))->newInstanceArgs($this->getConstructorArguments($beanie));
    }
}
