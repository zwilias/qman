<?php


namespace QMan;

use Psr\Log\LoggerInterface;

class ErrorHandler implements ShutdownHandlerInterface
{
    public function handleShutdown(Worker $worker, LoggerInterface $logger)
    {
        $error = error_get_last();
        $fatal = E_ERROR | E_USER_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_RECOVERABLE_ERROR;

        if (! empty($error) && $error['type'] & $fatal) {
            $logger->critical('Stopping worker because fatal error occurred', $error);
            $worker->stop();
        }
    }
}
