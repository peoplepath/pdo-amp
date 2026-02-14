<?php

declare(strict_types=1);

namespace PeoplePath\PdoApm\Subscriber;

use PeoplePath\PdoApm\Event;
use PeoplePath\PdoApm\Subscriber;

interface ExecutionFailedSubscriber extends Subscriber
{
    public function executionFailed(Event\ExecutionFailedEvent $event): void;
}
