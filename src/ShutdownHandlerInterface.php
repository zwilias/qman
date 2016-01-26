<?php


namespace QMan;


use Psr\Log\LoggerInterface;

interface ShutdownHandlerInterface
{
    /**
     * @param Worker $worker
     * @param LoggerInterface $logger
     * @return void
     */
    public function handleShutdown(Worker $worker, LoggerInterface $logger);
}
