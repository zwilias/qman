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
     * @var WorkerConfig
     */
    protected $workerConfig;

    public function __construct()
    {
        $this->logger = new NullLogger();
        $this->workerConfig = new WorkerConfig();
        $this->eventLoop = new EventLoop($this->logger);
    }

    /**
     * @param LoggerInterface $logger
     * @return $this
     */
    public function withLogger(LoggerInterface $logger)
    {
        $this->eventLoop->setLogger($logger);
        $this->logger = $logger;
        return $this;
    }

    /**
     * @param WorkerConfig $config
     * @return $this
     */
    public function withWorkerConfig(WorkerConfig $config)
    {
        $this->workerConfig = $config;
        return $this;
    }

    /**
     * @param EventLoop $eventLoop
     * @return $this
     */
    public function withEventLoop(EventLoop $eventLoop)
    {
        $this->eventLoop = $eventLoop;
        return $this;
    }

    /**
     * @param Beanie $beanie
     * @return Worker
     */
    public function build(Beanie $beanie)
    {
        return new Worker($beanie, $this->workerConfig, $this->eventLoop, $this->logger);
    }
}
