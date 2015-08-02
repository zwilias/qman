<?php


namespace QMan;


use Psr\Log\LoggerAwareInterface;

interface JobFailureStrategy extends ConfigAwareInterface, LoggerAwareInterface
{
    /**
     * @param Job $job
     * @return void
     */
    public function handleFailedJob(Job $job);
}
