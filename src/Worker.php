<?php


namespace QMan;


use Beanie\Beanie;
use Beanie\Job\Job;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class Worker implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var Beanie */
    protected $beanie;

    /** @var WorkerConfig */
    protected $config;

    /** @var EventLoop */
    protected $eventLoop;

    /** @var int */
    private $startTime;

    /**
     * @param Beanie $beanie
     * @param WorkerConfig $config
     * @param EventLoop $eventLoop
     * @param LoggerInterface $logger
     */
    public function __construct(Beanie $beanie, WorkerConfig $config, EventLoop $eventLoop, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->eventLoop = $eventLoop;

        $this->eventLoop->setJobListenerRemovedCallback([$this, 'removedJobListenerCallback']);
        $this->eventLoop->setJobReceivedCallback([$this, 'handleJob']);

        $this->beanie = $beanie;

        $this->config->lock();
    }

    public function run()
    {
        $this->startTime = time();
        $workers = $this->beanie->workers();

        $this->registerWatchers($workers);

        $this->eventLoop->run();

        $this->shutdown($workers);

        $this->logger->info('Termination sequence complete. I\'ll be back.');
    }

    /**
     * @return bool
     */
    public function checkTimeToLive()
    {
        return (time() - $this->startTime) >= $this->config->getMaxTimeAlive();
    }

    /**
     * @return bool
     */
    public function checkMaximalMemoryUsage()
    {
        return memory_get_usage(true) >= $this->config->getMaxMemoryUsage();
    }

    /**
     * @param \Beanie\Worker $worker
     */
    public function removedJobListenerCallback(\Beanie\Worker $worker)
    {
        $this->logger->notice('Scheduling reconnection', ['worker' => $worker]);
        $this->eventLoop->scheduleReconnection(
            3, 15, $worker
        );
    }

    /**
     * @param Job $job
     */
    public function handleJob(Job $job)
    {
        $this->logger->info('Handling job: #' . $job->getId());

        sleep(2);

        $this->logger->debug('Deleting job');

        $job->delete();
    }

    /**
     * @param $workers
     */
    protected function registerWatchers($workers)
    {
        array_map(function (\Beanie\Worker $worker) {
            $this->eventLoop->registerJobListener($worker);
        }, $workers);

        $this->eventLoop
            ->registerBreakCondition('time to live', [$this, 'checkTimeToLive'])
            ->registerBreakCondition('maximal memory usage', [$this, 'checkMaximalMemoryUsage']);

        array_map(function ($terminationSignal) {
            $this->eventLoop->registerBreakSignal($terminationSignal);
        }, $this->config->getTerminationSignals());
    }

    /**
     * @param $workers
     */
    protected function shutdown($workers)
    {
        array_map(function (\Beanie\Worker $worker) {
            try {
                $worker->quit();
            } catch (\Exception $exception) {
                $this->logger->warning('Failed to properly quit worker', ['worker' => $worker]);
            }
        }, $workers);
    }
}
