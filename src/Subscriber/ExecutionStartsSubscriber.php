<?php

declare(strict_types=1);

namespace PeoplePath\PdoApm\Subscriber;

use PeoplePath\PdoApm\Event;
use PeoplePath\PdoApm\Subscriber;

interface ExecutionStartsSubscriber extends Subscriber
{
    public function executionStarts(Event\ExecutionStartsEvent $event): void;
}
