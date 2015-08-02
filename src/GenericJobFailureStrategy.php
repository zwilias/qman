<?php


namespace QMan;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class GenericJobFailureStrategy implements JobFailureStrategy
{
    use LoggerAwareTrait,
        ConfigAwareTrait;

    /**
     * @param QManConfig $config
     * @param LoggerInterface|null $logger
     */
    public function __construct(QManConfig $config, LoggerInterface $logger = null)
    {
        $this->setConfig($config);
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * @param Job $job
     */
    public function handleFailedJob(Job $job)
    {
        $stats = $job->stats();

        $normalizedTries = (($stats['reserves'] - 1) % $this->config->getMaxTries()) + 1;

        if ($normalizedTries >= $this->config->getMaxTries()) {
            $job->bury($stats['pri']);
            $this->logger->warning('Job exceeded maximum tries, burying', ['job' => $job]);
        } else {
            $delay = ($this->config->getDefaultFailureDelay() * $normalizedTries);
            $job->release($stats['pri'], $delay);
            $this->logger->notice('Releasing job with delay', ['job' => $job, 'delay' => $delay]);
        }
    }
}
