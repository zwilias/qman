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
        if (!extension_loaded('ev')) {
            throw new \Exception('Missing extension: ev');
        }

        $this->logger = $logger ?: new NullLogger();
        $this->config = $config ?: new WorkerConfig();
        $this->eventLoop = $eventLoop ?: new EventLoop($this->logger);

        $this->beanie = $beanie;

        $this->config->lock();
    }

    public function run()
    {
        $workers = $this->beanie->workers();
        $this->startTime = time();

        array_map(function (\Beanie\Worker $worker) {
            $this->eventLoop->registerJobListener($worker, [$this, 'handleJob']);
        }, $workers);

        $this->eventLoop
            ->registerBreakCondition('time to live', function () {
                return (time() - $this->startTime) > $this->config->getMaxTimeAlive();
            })
            ->registerBreakCondition('maximal memory usage', function () {
                return memory_get_usage(true) > $this->config->getMaxMemoryUsage();
            })
            ->registerBreakSignal($this->config->getTerminationSignal());

        $this->eventLoop->run();

        array_map(function (\Beanie\Worker $worker) {
            $worker->quit();
        }, $workers);

        $this->logger->info('Termination sequence complete. I\'ll be back.');
    }

    /**
     * @param Job $job
     */
    public function handleJob(Job $job)
    {
        $this->logger->info('Handling job: #' . $job->getId());

        sleep(2);

        $this->logger->debug('releasing job');

        $job->release(Beanie::DEFAULT_PRIORITY, rand(5, 15));
    }
}
