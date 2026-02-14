<?php

declare(strict_types=1);

namespace PeoplePath\PdoApm\Subscriber;

use PeoplePath\PdoApm\Event;
use PeoplePath\PdoApm\Subscriber;

interface PrepareSubscriber extends Subscriber
{
    public function prepare(Event\PrepareEvent $event): void;
}
