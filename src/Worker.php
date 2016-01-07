<?php


namespace QMan;


use Beanie\Beanie;
use Beanie\Job\Job as BeanieJob;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class Worker implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var Beanie */
    protected $beanie;

    /** @var QManConfig */
    protected $config;

    /** @var EventLoop */
    protected $eventLoop;

    /**
     * @var CommandSerializerInterface
     */
    protected $commandSerializer;

    /** @var int */
    private $startTime;

    /**
     * @var JobFailureStrategyInterface
     */
    protected $jobFailureStrategy;

    /**
     * @param Beanie $beanie
     * @param QManConfig $config
     * @param EventLoop $eventLoop
     * @param CommandSerializerInterface $commandSerializer
     * @param JobFailureStrategyInterface $jobFailureStrategy
     * @param LoggerInterface $logger
     */
    public function __construct(
        Beanie $beanie,
        QManConfig $config,
        EventLoop $eventLoop,
        CommandSerializerInterface $commandSerializer,
        JobFailureStrategyInterface $jobFailureStrategy,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->config = $config;
        $this->eventLoop = $eventLoop;
        $this->commandSerializer = $commandSerializer;
        $this->jobFailureStrategy = $jobFailureStrategy;

        $this->eventLoop->setJobListenerRemovedCallback([$this, 'removedJobListenerCallback']);
        $this->eventLoop->setJobReceivedCallback([$this, 'handleJob']);

        $this->beanie = $beanie;

        $this->config->lock();
        $this->jobFailureStrategy = $jobFailureStrategy;
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
     * @param BeanieJob $beanieJob
     * @return Job
     */
    public function createJobFromBeanieJob(BeanieJob $beanieJob)
    {
        return new Job($beanieJob, $this->commandSerializer->unserialize($beanieJob->getData()));
    }

    /**
     * @param BeanieJob $beanieJob
     */
    public function handleJob(BeanieJob $beanieJob)
    {
        $job = $this->createJobFromBeanieJob($beanieJob);

        $result = false;

        try {
            $result = $job->execute();
        } catch (\Exception $exception) {
            $this->logger->critical('Job threw exception', ['job' => $job, 'exception' => $exception]);
        }

        if ($result === false) {
            $this->jobFailureStrategy->handleFailedJob($job);
        } else {
            $job->delete();
        }
    }

    /**
     * @param $workers
     */
    protected function registerWatchers($workers)
    {
        array_map(function (\Beanie\Worker $worker) {
            $worker->getTubeStatus()->setWatchedTubes(
                $this->config->getWatchedTubes()
            );

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
