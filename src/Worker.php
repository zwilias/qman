<?php


namespace QMan;


use Beanie\Beanie;
use Beanie\Job\Job;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Worker implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var string */
    protected $serverName;

    /** @var Beanie */
    protected $beanie;

    /** @var WorkerConfig */
    protected $config;

    /** @var ShutdownHandler */
    protected $shutdownHandler;

    /**
     * @param $serverName
     * @param Beanie $beanie
     * @param WorkerConfig|null $config
     * @param ShutdownHandler|null $shutdownHandler
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        $serverName,
        Beanie $beanie,
        WorkerConfig $config = null,
        ShutdownHandler $shutdownHandler = null,
        LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?: new NullLogger();
        $this->config = $config ?: new WorkerConfig();
        $this->shutdownHandler = $shutdownHandler ?: new ShutdownHandler($this->config, $this->logger);

        $this->beanie = $beanie;

        $this->config->lock();
    }

    public function run()
    {
        $this->shutdownHandler->start();
        $worker = $this->beanie->worker($this->serverName);

        while ($this->shouldLoop()) {
            $this->shutdownHandler->pollSignals();
            $this->logger->debug('Reserving job');

            if (($job = $worker->reserve($this->config->getReserveTimeout())) == null) {
                $this->logger->debug('No job, resuming');
                continue;
            }

            $this->logger->info('Received job: #' . $job->getId());
            $this->shutdownHandler->pollSignals($job);
            $this->handleJob($job);
        }
    }

    /**
     * @return bool
     */
    protected function shouldLoop()
    {
        return true;
    }

    /**
     * @param Job $job
     */
    public function handleJob(Job $job)
    {
        $this->logger->debug('Handling job: #' . $job->getId());
        $job->release(Beanie::DEFAULT_PRIORITY, rand(5, 15));
    }

}
