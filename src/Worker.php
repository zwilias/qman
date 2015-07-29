<?php


namespace QMan;


use Beanie\Beanie;
use Beanie\Exception\Exception;
use Beanie\Exception\SocketException;
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

    private $watchers = [];

    /**
     * @param $serverName
     * @param Beanie $beanie
     * @param WorkerConfig|null $config
     * @param ShutdownHandler|null $shutdownHandler
     * @param LoggerInterface|null $logger
     * @throws \Exception
     */
    public function __construct(
        $serverName,
        Beanie $beanie,
        WorkerConfig $config = null,
        ShutdownHandler $shutdownHandler = null,
        LoggerInterface $logger = null
    ) {
        if (!extension_loaded('ev')) {
            throw new \Exception('Missing extension: ev');
        }

        $this->logger = $logger ?: new NullLogger();
        $this->config = $config ?: new WorkerConfig();
        $this->shutdownHandler = $shutdownHandler ?: new ShutdownHandler($this->config, $this->logger);

        $this->beanie = $beanie;

        $this->config->lock();
    }

    private function listenForJobs(\Beanie\Worker $worker) {
        $jobOath = $worker->reserveOath();
        //socket_set_nonblock($jobOath->getSocket());

        $this->logger->debug('Creating event watcher for socket');

        return new \EvIo($jobOath->getSocket(), \Ev::READ, function ($watcher) use ($worker, $jobOath) {
            $this->logger->debug('Incoming event for socket');

            try {
                $this->handleJob($jobOath->invoke());
            } catch (SocketException $socketException) {
                if (in_array($socketException->getCode(), [
                    (SOCKET_EAGAIN | SOCKET_EWOULDBLOCK),
                    SOCKET_EINPROGRESS
                ])) {
                    return;
                }
            } catch (Exception $ex) {
                $this->logger->error($ex->getCode() . ': ' . $ex->getMessage());
                $watcher->stop();
                return;
            }

            $watcher->stop();
            array_push($this->watchers, $this->listenForJobs($worker));
            array_splice($this->watchers, array_search($watcher, $this->watchers), 1);
        }, [], \Ev::MINPRI);
    }

    public function run()
    {
        //$this->shutdownHandler->start();
        $workers = $this->beanie->workers();

        $this->watchers[] = new \EvSignal($this->config->getTerminationSignal(), function ($watcher) {
            $this->logger->debug('Incoming signal');
            foreach ($this->watchers as $watcher) {
                $watcher->stop();
            }
            \Ev::stop(\Ev::BREAK_ALL);
            $this->shutdownHandler->handleShutdown();
        }, \Ev::MAXPRI);

        $this->watchers[] = new \EvTimer(5, 5, function () {
            $this->logger->debug('Still alive...');
        });

        foreach ($workers as $worker) {
            $this->watchers[] = $this->listenForJobs($worker);
        }

        \Ev::run();

        $this->logger->info('Broke out of event loop');
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
        $this->logger->info('Handling job: #' . $job->getId());

        sleep(2);

        $this->logger->debug('releasing job');

        $job->release(Beanie::DEFAULT_PRIORITY, rand(5, 15));
    }

}
