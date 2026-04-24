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
        $errorInfo = $pdo->errorInfo();
        /** @var string|null $state */
        $state = $errorInfo[0] ?? '';
        /** @var int|null $code */
        $code = $errorInfo[1] ?? 0;
        /** @var string|null $message */
        $message = $errorInfo[2] ?? '';

        return new self(new PDOException("SQLSTATE[{$state}]: {$message}", (int) $code), $params);
    }

    public function notifySubscriber(Subscriber $subscriber): void
    {
        if ($subscriber instanceof Subscriber\ExecutionFailedSubscriber) {
            $subscriber->executionFailed($this);
        }
    }
}
