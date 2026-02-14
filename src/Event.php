<?php

declare(strict_types=1);

namespace PeoplePath\PdoApm;

interface Event
{
    public function notifySubscriber(Subscriber $subscriber): void;
}
