<?php

declare(strict_types=1);

namespace PeoplePath\PdoApm\Event;

use PeoplePath\PdoApm\Event;
use PeoplePath\PdoApm\Subscriber;

final class TransactionRollbackEvent implements Event
{
    public function notifySubscriber(Subscriber $subscriber): void
    {
        if ($subscriber instanceof Subscriber\TransactionRollbackSubscriber) {
            $subscriber->transactionRollback($this);
        }
    }
}
