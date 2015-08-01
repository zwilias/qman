<?php


namespace QMan;


use Beanie\Exception\SocketException;
use Beanie\Job\JobOath;
use Beanie\Worker as BeanieWorker;
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
     * @var callable[]
     */
    protected $breakConditions = [];

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

        if (($location = array_search($watcher, $this->watchers, true)) !== false) {
            array_splice($this->watchers, $location, 1);
        }
    }

    public function registerJobListener(
        BeanieWorker $worker, callable $receivedJobCallback, callable $removedListenerCallback
    ) {
        $this->logger->info('Registering job listener', ['worker' => $worker]);
        $jobOath = $worker->reserveOath();

        $this->registerWatcher(new \EvIo(
            $jobOath->getSocket(),
            \Ev::READ,
            function (\EvWatcher $watcher) use ($worker, $jobOath, $receivedJobCallback, $removedListenerCallback) {
                $this->logger->debug('Incoming socket event', ['worker' => $worker]);

                $this->handleIncomingJob($watcher, $worker, $jobOath, $receivedJobCallback, $removedListenerCallback);
            }
        ));

        return $this;
    }

    protected function handleIncomingJob(
        \EvWatcher $watcher,
        BeanieWorker $worker,
        JobOath $jobOath,
        callable $receivedJobCallback,
        callable $removedListenerCallback
    ) {
        pcntl_sigprocmask(SIG_BLOCK, $this->terminationSignals);

        try {
            $job = $jobOath->invoke();
            $receivedJobCallback($job);
        } catch (SocketException $socketException) {
            $this->logger->error($socketException->getCode() . ': ' . $socketException->getMessage());
            $this->removeWatcher($watcher);

            $this->removeJobListener($worker, $receivedJobCallback, $removedListenerCallback);

            return;
        } catch (\Exception $exception) {
            $this->logger->error($exception->getCode() . ': ' . $exception->getMessage());
            $this->removeWatcher($watcher);

            return;
        } finally {
            pcntl_sigprocmask(SIG_UNBLOCK, $this->terminationSignals);
        }

        $worker->reserveOath();
    }

    public function removeJobListener(
        BeanieWorker $worker, callable $receivedJobCallback, callable $removedListenerCallback
    ) {
        $this->logger->alert('Removing job listener', ['worker' => $worker]);

        $worker->disconnect();

        $removedListenerCallback($worker, $receivedJobCallback);
    }

    public function scheduleReconnection(
        $after, $every, BeanieWorker $worker, callable $receivedJobCallback, callable $removedListenerCallback
    ) {
        $this->registerWatcher(
            new \EvTimer($after, $every,
                function (\EvWatcher $watcher) use ($worker, $receivedJobCallback, $removedListenerCallback) {
                    try {
                        $this->logger->info('Attempting reconnection', ['worker' => $worker]);
                        $worker->reconnect();

                        $this->removeWatcher($watcher);
                        $this->registerJobListener($worker, $receivedJobCallback, $receivedJobCallback);
                    } catch (SocketException $socketException) {
                        $this->logger->warning(
                            'Failed to reconnect',
                            ['worker' => $worker, 'exception' => $socketException]
                        );
                    }
                }
            )
        );
    }

    public function registerBreakCondition($name, callable $condition)
    {
        $this->breakConditions[$name] = $condition;

        if (count($this->breakConditions) === 1) {
            $this->registerWatcher(new \EvTimer(10, 10, function () {
                foreach ($this->breakConditions as $name => $condition) {
                    if ($condition() !== false) {
                        $this->logger->info(
                            'Noticed breaking condition',
                            ['name' => $name, 'condition' => $condition]
                        );
                        $this->stop();
                    }
                }

                $this->logger->debug('Checked all break-conditions');
            }));
        }

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

    /**
     * @return \EvWatcher[]
     */
    public function getWatchers()
    {
        return $this->watchers;
    }

    public function __destruct()
    {
        foreach ($this->watchers as $watcher) {
            $this->removeWatcher($watcher);

            unset($watcher);
        }
    }
}
