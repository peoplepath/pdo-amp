<?php

declare(strict_types=1);

namespace PeoplePath\PdoApm\Subscriber;

use PeoplePath\PdoApm\Event;
use PeoplePath\PdoApm\Subscriber;

interface TransactionBeginSubscriber extends Subscriber
{
    public function transactionBegin(Event\TransactionBeginEvent $event): void;
}
