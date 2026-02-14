<?php

declare(strict_types=1);

namespace PeoplePath\PdoApm\Event;

use PDOException;
use PeoplePath\PdoApm\Event;
use PeoplePath\PdoApm\PDO;
use PeoplePath\PdoApm\Subscriber;

final readonly class ExecutionFailedEvent implements Event
{
    /**
     * @param  array<int|string, mixed>|null  $params
     */
    public function __construct(
        public PDOException $exception,
        public ?array $params = null,
    ) {}

    /**
     * @param  array<int|string, mixed>|null  $params
     */
    public static function fromError(PDO $pdo, ?array $params = null): self
    {
        [$state, $code, $message] = $pdo->errorInfo();

        return new self(new PDOException("SQLSTATE[$state]: {$message}", $code), $params);
    }

    public function notifySubscriber(Subscriber $subscriber): void
    {
        if ($subscriber instanceof Subscriber\ExecutionFailedSubscriber) {
            $subscriber->executionFailed($this);
        }
    }
}
