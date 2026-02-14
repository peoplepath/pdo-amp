<?php

declare(strict_types=1);

namespace PeoplePath\PdoApm\Event;

use PeoplePath\PdoApm\Event;
use PeoplePath\PdoApm\Subscriber;

final readonly class ExecutionSucceededEvent implements Event
{
    /**
     * @param  array<int|string, mixed>|null  $params
     */
    public function __construct(
        public int $rowCount,
        public ?array $params = null,
    ) {}

    public function notifySubscriber(Subscriber $subscriber): void
    {
        if ($subscriber instanceof Subscriber\ExecutionSucceededSubscriber) {
            $subscriber->executionSucceeded($this);
        }
    }
}
