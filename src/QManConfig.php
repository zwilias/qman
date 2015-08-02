<?php


namespace QMan;


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

    /** @var int */
    private $maxMemoryUsage = self::DEFAULT_MAX_MEMORY_USAGE;

    /** @var int */
    private $maxTimeAlive = self::DEFAULT_MAX_TIME_ALIVE;

    /** @var int[] */
    private $terminationSignals = [self::DEFAULT_TERMINATION_SIGNAL];

    /** @var bool */
    private $locked = false;

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
