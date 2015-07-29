<?php


namespace QMan;


use Beanie\Job\Job;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ShutdownHandler implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var WorkerConfig */
    protected $config;

    /** @var int */
    protected $startTime;

    /** @var bool */
    protected $started = false;

    /** @var Job */
    protected $job;

    /**
     * @param WorkerConfig $config
     * @param LoggerInterface $logger
     */
    public function __construct(WorkerConfig $config = null, LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: new NullLogger();
        $this->config = $config ?: new WorkerConfig();

        $this->config->lock();
    }

    public function start()
    {
        $this->startTime = time();
        $this->started = true;

        pcntl_signal($this->config->getTerminationSignal(), [$this, 'handleShutDown'], false);
        $this->logger->info('Signal handler attached');

        $this->closeSignalWindow();
    }

    /**
     * @param Job|null $job
     */
    public function pollSignals(Job $job = null)
    {
        // Keep the job around so we can perform cleanup in $this->shutDown() if the signal handler calls it
        $this->job = $job;

        $this->checkSignals();
        $this->checkMemoryUsage();
        $this->checkTimeToLive();

        // Don't needlessly keep the job around
        $this->job = null;
    }

    private function checkSignals()
    {
        $this->openSignalWindow();
        pcntl_signal_dispatch();
        $this->closeSignalWindow();
    }

    private function checkMemoryUsage()
    {
        if (memory_get_usage() > $this->config->getMaxMemoryUsage()) {
            $this->logger->warning('Max memory usage reached, shutting down.');
            $this->handleShutdown();
        }
    }

    private function checkTimeToLive()
    {
        if ((time() - $this->startTime) > $this->config->getMaxTimeAlive()) {
            $this->logger->warning('Max time to live reached, shutting down.');
            $this->handleShutdown();
        }
    }

    private function closeSignalWindow()
    {
        pcntl_sigprocmask(SIG_BLOCK, [$this->config->getTerminationSignal()]);
        $this->logger->debug('Signal window closed');
    }

    private function openSignalWindow()
    {
        pcntl_sigprocmask(SIG_UNBLOCK, [$this->config->getTerminationSignal()]);
        $this->logger->debug('Signal window opened');
    }

    /**
     * @throws ExitException
     */
    public function handleShutdown()
    {
        if ($this->job && $this->job->getState() == Job::STATE_RESERVED) {
            $this->job->release();
            $this->logger->info('Job #' . $this->job->getId() . ' released');
        }

        $this->logger->info('Termination sequence complete. I\'ll be back.');
    }
}
