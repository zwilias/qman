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
     * @param WorkerConfig|null $config
     * @param EventLoop $eventLoop
     * @param LoggerInterface|null $logger
     * @throws \Exception
     */
    public function __construct(
        Beanie $beanie,
        WorkerConfig $config = null,
        EventLoop $eventLoop = null,
        LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?: new NullLogger();
        $this->config = $config ?: new WorkerConfig();
        $this->eventLoop = $eventLoop ?: new EventLoop(
            $this->logger, [$this, 'handleJob'], [$this, 'removedJobListenerCallback']
        );

        $this->beanie = $beanie;

        $this->config->lock();
    }

    public function run()
    {
        $workers = $this->beanie->workers();
        $this->startTime = time();

        array_map(function (\Beanie\Worker $worker) {
            $this->eventLoop->registerJobListener($worker);
        }, $workers);

        $this->eventLoop
            ->registerBreakCondition('time to live', [$this, 'checkTimeToLive'])
            ->registerBreakCondition('maximal memory usage', [$this, 'checkMaximalMemoryUsage']);

        array_map(function ($terminationSignal) {
            $this->eventLoop->registerBreakSignal($terminationSignal);
        }, $this->config->getTerminationSignals());

        $this->eventLoop->run();

        array_map(function (\Beanie\Worker $worker) {
            try {
                $worker->quit();
            } catch (\Exception $exception) {
                $this->logger->warning('Failed to properly quit worker', ['worker' => $worker]);
            }
        }, $workers);

        $this->logger->info('Termination sequence complete. I\'ll be back.');
    }

    /**
     * @return int
     */
    protected function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * @return bool
     */
    public function checkTimeToLive()
    {
        return (time() - $this->getStartTime()) > $this->config->getMaxTimeAlive();
    }

    /**
     * @return bool
     */
    public function checkMaximalMemoryUsage()
    {
        return memory_get_usage(true) > $this->config->getMaxMemoryUsage();
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
}
