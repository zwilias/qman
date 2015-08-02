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
     * @var callable
     */
    protected $jobReceivedCallback;

    /**
     * @var callable
     */
    protected $jobListenerRemovedCallback;

    /**
     * @param LoggerInterface|null $logger
     * @throws \Exception
     */
    public function __construct(LoggerInterface $logger = null) {
        $this->logger = $logger ?: new NullLogger();
        $this->jobReceivedCallback = function () {};
        $this->jobListenerRemovedCallback =  function () {};
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

    /**
     * @param BeanieWorker $worker
     * @return $this
     */
    public function registerJobListener(BeanieWorker $worker)
    {
        $this->logger->info('Registering job listener', ['worker' => $worker]);
        $jobOath = $worker->reserveOath();

        $this->registerWatcher(new \EvIo(
            $jobOath->getSocket(),
            \Ev::READ,
            function (\EvWatcher $watcher) use ($worker, $jobOath) {
                $this->logger->debug('Incoming socket event', ['worker' => $worker]);

                $this->handleIncomingJob($watcher, $worker, $jobOath);
            }
        ));

        return $this;
    }

    /**
     * @param \EvWatcher $watcher
     * @param BeanieWorker $worker
     * @param JobOath $jobOath
     */
    protected function handleIncomingJob(
        \EvWatcher $watcher,
        BeanieWorker $worker,
        JobOath $jobOath
    ) {
        pcntl_sigprocmask(SIG_BLOCK, $this->terminationSignals);

        try {
            $job = $jobOath->invoke();
            call_user_func($this->jobReceivedCallback, $job);
        } catch (SocketException $socketException) {
            $this->logger->error($socketException->getCode() . ': ' . $socketException->getMessage());
            $this->removeWatcher($watcher);

            $this->removeJobListener($worker);

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

    /**
     * @param BeanieWorker $worker
     */
    public function removeJobListener(
        BeanieWorker $worker
    ) {
        $this->logger->alert('Removing job listener', ['worker' => $worker]);

        $worker->disconnect();

        call_user_func($this->jobListenerRemovedCallback, $worker);
    }

    /**
     * @param int $after
     * @param int $every
     * @param BeanieWorker $worker
     */
    public function scheduleReconnection(
        $after, $every, BeanieWorker $worker
    ) {
        $this->registerWatcher(
            new \EvTimer($after, $every, function (\EvWatcher $watcher) use ($worker) {
                $this->attemptReconnection($watcher, $worker);
            })
        );
    }

    /**
     * @param \EvWatcher $watcher
     * @param BeanieWorker $worker
     */
    public function attemptReconnection(\EvWatcher $watcher, BeanieWorker $worker)
    {
        try {
            $this->logger->info('Attempting reconnection', ['worker' => $worker]);
            $worker->reconnect();

            $this->removeWatcher($watcher);
            $this->registerJobListener($worker);
        } catch (SocketException $socketException) {
            $this->logger->warning(
                'Failed to reconnect',
                ['worker' => $worker, 'exception' => $socketException]
            );
        }
    }

    /**
     * @param string $name
     * @param callable $condition
     * @return $this
     */
    public function registerBreakCondition($name, callable $condition)
    {
        if (count($this->breakConditions) === 0) {
            $this->setupBreakConditionTimerWatcher();
        }

        $this->breakConditions[$name] = $condition;

        return $this;
    }

    protected function setupBreakConditionTimerWatcher()
    {
        $this->registerWatcher(new \EvTimer(10, 10, [$this, 'checkBreakConditions']));
    }

    public function checkBreakConditions()
    {
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
    }

    /**
     * @param int $signal
     * @return $this
     */
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

    /**
     * @param int $mode
     */
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

    /**
     * @param callable $jobReceivedCallback
     * @return EventLoop
     */
    public function setJobReceivedCallback(callable $jobReceivedCallback)
    {
        $this->jobReceivedCallback = $jobReceivedCallback;
        return $this;
    }

    /**
     * @param callable $jobListenerRemovedCallback
     * @return EventLoop
     */
    public function setJobListenerRemovedCallback(callable $jobListenerRemovedCallback)
    {
        $this->jobListenerRemovedCallback = $jobListenerRemovedCallback;
        return $this;
    }
}
