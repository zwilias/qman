<?php


namespace QMan;


use Beanie\Beanie;
use Beanie\Job\Job as BeanieJob;

class Job
{
    protected $job;
    protected $command;

    /**
     * @param BeanieJob $job
     * @param Command $command
     */
    public function __construct(BeanieJob $job, Command $command)
    {
        $this->job = $job;
        $this->command = $command;
    }

    /**
     * @return array
     */
    public function stats()
    {
        return $this->job->stats();
    }

    /**
     * @param int $priority
     * @return $this
     */
    public function bury($priority = Beanie::DEFAULT_PRIORITY)
    {
        $this->job->bury($priority);
        return $this;
    }

    /**
     * @param int $priority
     * @param int $delay
     * @return $this
     */
    public function release($priority = Beanie::DEFAULT_PRIORITY, $delay = Beanie::DEFAULT_DELAY)
    {
        $this->job->release($priority, $delay);
        return $this;
    }

    /**
     * @return $this
     */
    public function delete()
    {
        $this->job->delete();
        return $this;
    }

    /**
     * @return bool
     */
    public function execute()
    {
        return $this->command->execute();
    }
}
