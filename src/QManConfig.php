<?php


namespace QMan;


use Beanie\Beanie;

class QManConfig
{
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

    /**
     * Default: how many times a job should be allowed to fail before being buried
     */
    const DEFAULT_MAX_TRIES = 3;

    /**
     * Default: after failure, the job will be released with a certain delay
     */
    const DEFAULT_FAILURE_DELAY = 60;

    /**
     * Default: the default list of tubes to watch
     */
    const DEFAULT_WATCHED_TUBE = Beanie::DEFAULT_TUBE;

    /** @var int */
    private $maxMemoryUsage = self::DEFAULT_MAX_MEMORY_USAGE;

    /** @var int */
    private $maxTimeAlive = self::DEFAULT_MAX_TIME_ALIVE;

    /** @var int[] */
    private $terminationSignals = [self::DEFAULT_TERMINATION_SIGNAL];

    /** @var bool */
    private $locked = false;

    /** @var int */
    private $maxTries = self::DEFAULT_MAX_TRIES;

    /** @var int */
    private $defaultFailureDelay = self::DEFAULT_FAILURE_DELAY;

    /** @var string[] */
    private $watchedTubes = [self::DEFAULT_WATCHED_TUBE];

    /**
     * @return int
     */
    public function getMaxMemoryUsage()
    {
        return $this->maxMemoryUsage;
    }

    /**
     * @param int $maxMemoryUsage
     * @return QManConfig
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
     * @return QManConfig
     */
    public function setMaxTimeAlive($maxTimeAlive)
    {
        $this->checkLock()->maxTimeAlive = $maxTimeAlive;

        return $this;
    }

    /**
     * @return int[]
     */
    public function getTerminationSignals()
    {
        return $this->terminationSignals;
    }

    /**
     * @param int[] $terminationSignals
     * @return QManConfig
     */
    public function setTerminationSignals(array $terminationSignals)
    {
        $this->checkLock()->terminationSignals = $terminationSignals;

        return $this;
    }

    /**
     * @return int
     */
    public function getMaxTries()
    {
        return $this->maxTries;
    }

    /**
     * @param int $maxTries
     * @return $this
     */
    public function setMaxTries($maxTries)
    {
        $this->checkLock()->maxTries = $maxTries;
        return $this;
    }

    /**
     * @return int
     */
    public function getDefaultFailureDelay()
    {
        return $this->defaultFailureDelay;
    }

    /**
     * @param int $defaultFailureDelay
     * @return $this
     */
    public function setDefaultFailureDelay($defaultFailureDelay)
    {
        $this->checkLock()->defaultFailureDelay = $defaultFailureDelay;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getWatchedTubes()
    {
        return $this->watchedTubes;
    }

    /**
     * @param string[] $watchedTubes
     * @return QManConfig
     */
    public function setWatchedTubes($watchedTubes)
    {
        $this->checkLock()->watchedTubes = $watchedTubes;
        return $this;
    }

    /**
     * @return QManConfig
     */
    public function lock()
    {
        $this->locked = true;

        return $this;
    }

    /**
     * @throws \BadMethodCallException When attempting to change a property after locking the config object down.
     * @return QManConfig
     */
    protected function checkLock()
    {
        if ($this->locked !== false) {
            throw new \BadMethodCallException('Setting properties on a config object after it has been locked is not allowed.');
        }

        return $this;
    }
}
