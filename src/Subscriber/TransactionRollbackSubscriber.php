<?php

declare(strict_types=1);

namespace PeoplePath\PdoApm\Subscriber;

use PeoplePath\PdoApm\Event;
use PeoplePath\PdoApm\Subscriber;

interface TransactionRollbackSubscriber extends Subscriber
{
    public function transactionRollback(Event\TransactionRollbackEvent $event): void;
}
