<?php


namespace QMan;


class WorkerConfig
{
    /**
     * Default: wait 10 seconds for beanstalk to provide us with something to do before checking for incoming signals
     */
    const DEFAULT_RESERVE_TIMEOUT = 10;

    /**
     * Default: commit suicide whenever out memory usage reaches over 20MB
     */
    const DEFAULT_MAX_MEMORY_USAGE = 20000000;

    /**
     * Default: commit suicide every 24 hours
     */
    const DEFAULT_MAX_TIME_ALIVE = 86400;

    /**
     * Default: graceful termination signal we periodically check for
     */
    const DEFAULT_TERMINATION_SIGNAL = SIGTERM;

    /** @var int */
    private $reserveTimeout = self::DEFAULT_RESERVE_TIMEOUT;

    /** @var int */
    private $maxMemoryUsage = self::DEFAULT_MAX_MEMORY_USAGE;

    /** @var int */
    private $maxTimeAlive = self::DEFAULT_MAX_TIME_ALIVE;

    /** @var int */
    private $terminationSignal = self::DEFAULT_TERMINATION_SIGNAL;

    /** @var bool */
    private $locked = false;

    /**
     * @return int
     */
    public function getReserveTimeout()
    {
        return $this->reserveTimeout;
    }

    /**
     * @param int $reserveTimeout
     * @return WorkerConfig
     */
    public function setReserveTimeout($reserveTimeout)
    {
        $this->checkLock()->reserveTimeout = $reserveTimeout;

        return $this;
    }

    /**
     * @return int
     */
    public function getMaxMemoryUsage()
    {
        return $this->maxMemoryUsage;
    }

    /**
     * @param int $maxMemoryUsage
     * @return WorkerConfig
     */
    public function setMaxMemoryUsage($maxMemoryUsage)
    {
        $this->checkLock()->maxMemoryUsage = $maxMemoryUsage;

        return $this;
    }

    /**
     * @return int
     */
    public function getMaxTimeAlive()
    {
        return $this->maxTimeAlive;
    }

    /**
     * @param int $maxTimeAlive
     * @return WorkerConfig
     */
    public function setMaxTimeAlive($maxTimeAlive)
    {
        $this->checkLock()->maxTimeAlive = $maxTimeAlive;

        return $this;
    }

    /**
     * @return int
     */
    public function getTerminationSignal()
    {
        return $this->terminationSignal;
    }

    /**
     * @param int $terminationSignal
     * @return WorkerConfig
     */
    public function setTerminationSignal($terminationSignal)
    {
        $this->checkLock()->terminationSignal = $terminationSignal;

        return $this;
    }

    /**
     * @return WorkerConfig
     */
    public function lock()
    {
        $this->locked = true;

        return $this;
    }

    /**
     * @throws \BadMethodCallException When attempting to change a property after locking the config object down.
     * @return WorkerConfig
     */
    protected function checkLock()
    {
        if ($this->locked !== false) {
            throw new \BadMethodCallException('Setting properties on a config object after it has been locked is not allowed.');
        }

        return $this;
    }
}
