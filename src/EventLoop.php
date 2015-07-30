<?php


namespace QMan;


use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class EventLoop implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var NullLogger */
    protected $logger;

    /**
     * @param LoggerInterface|null $logger
     * @throws \Exception
     */
    public function __construct(LoggerInterface $logger = null)
    {
        if (!extension_loaded('ev')) {
            throw new \Exception('Missing extension: ev');
        }

        $this->logger = $logger ?: new NullLogger();
    }

    public function addWatcher(\EvWatcher $watcher)
    {
        
    }

    public function addJobListener(Worker $worker, callable $callback)
    {
        
    }

    public function stop()
    {
        
    }
}
