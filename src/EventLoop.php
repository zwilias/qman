<?php


namespace QMan;


use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class EventLoop implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var \EvWatcher[] */
    protected $watchers = [];

    /**
     * @var int[]
     */
    protected $terminationSignals = [];

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

    /**
     * @param \EvWatcher $watcher
     */
    public function registerWatcher(\EvWatcher $watcher)
    {
        array_push($this->watchers, $watcher);
    }

    /**
     * @param \EvWatcher $watcher
     */
    public function removeWatcher(\EvWatcher $watcher)
    {
        $watcher->stop();

        if (($location = array_search($watcher, $this->watchers)) !== false) {
            array_splice($this->watchers, $location, 1);
        }
    }

    public function registerJobListener(\Beanie\Worker $worker, callable $callback)
    {
        $jobOath = $worker->reserveOath();

        $this->registerWatcher(new \EvIo(
            $jobOath->getSocket(),
            \Ev::READ,
            function (\EvWatcher $watcher) use ($worker, $jobOath, $callback) {
                $this->logger->debug('Incoming event from server: ' . $worker->getServer());

                pcntl_sigprocmask(SIG_BLOCK, $this->terminationSignals);

                try {
                    $callback($jobOath->invoke());
                } catch (\Exception $ex) {
                    $this->logger->error($ex->getCode() . ': ' . $ex->getMessage());
                }

                pcntl_sigprocmask(SIG_UNBLOCK, $this->terminationSignals);

                $this->removeWatcher($watcher);
                $this->registerJobListener($worker, $callback);
            }
        ));

        return $this;
    }

    public function registerBreakCondition($name, callable $condition)
    {
        $this->registerWatcher(new \EvTimer(5, 5, function () use ($name, $condition) {
            if ($condition() !== false) {
                $this->logger->info('Noticed breaking condition: ' . $name);
            } else {
                $this->logger->debug('Checked ' . $name . ', nothing to see here.');
            }
        }));

        return $this;
    }

    public function registerBreakSignal($signal)
    {
        $this->terminationSignals[] = $signal;

        $this->registerWatcher(new \EvSignal(
            $signal,
            function () {
                $this->logger->info('Received breaking signal.');
                $this->stop();
            }
        ));

        return $this;
    }

    public function run($mode = \Ev::FLAG_AUTO)
    {
        $this->logger->info('Starting event loop.');
        \Ev::run($mode);
    }

    /**
     * Stops the event loop, by stopping all the watchers and breaking out of the event loop.
     */
    public function stop()
    {
        foreach ($this->watchers as $watcher) {
            $watcher->stop();
        }

        \Ev::stop(\Ev::BREAK_ALL);
    }
}
