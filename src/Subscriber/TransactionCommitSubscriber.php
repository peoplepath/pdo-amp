<?php

declare(strict_types=1);

namespace PeoplePath\PdoApm\Subscriber;

use PeoplePath\PdoApm\Event;
use PeoplePath\PdoApm\Subscriber;

interface TransactionCommitSubscriber extends Subscriber
{
    public function transactionCommit(Event\TransactionCommitEvent $event): void;
}
