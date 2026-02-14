<?php

declare(strict_types=1);

namespace PeoplePath\PdoApm\Event;

use PeoplePath\PdoApm\Event;
use PeoplePath\PdoApm\Subscriber;

final readonly class ExecutionStartsEvent implements Event
{
    public function __construct(
        public string $query,
    ) {}

    public function notifySubscriber(Subscriber $subscriber): void
    {
        if ($subscriber instanceof Subscriber\ExecutionStartsSubscriber) {
            $subscriber->executionStarts($this);
        }
    }
}
