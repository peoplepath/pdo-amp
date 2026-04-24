<?php

declare(strict_types=1);

namespace PeoplePath\PdoApm;

use PDOException;

class PDO extends \PDO
{
    /** @var Subscriber[] */
    private array $subscribers = [];

    /** @phpstan-param array<PDO::ATTR_*, mixed>|null $options */
    public function __construct(
        string $dns,
        ?string $username = null,
        ?string $password = null,
        ?array $options = null,
    ) {
        parent::__construct($dns, $username, $password, $options);
    }

    public function beginTransaction(): bool
    {
        if ($result = parent::beginTransaction()) {
            $this->notifySubscribers(new Event\TransactionBeginEvent);
        }

        return $result;
    }

    public function commit(): bool
    {
        if ($result = parent::commit()) {
            $this->notifySubscribers(new Event\TransactionCommitEvent);
        }

        return $result;
    }

    public function exec(string $statement): int|false
    {
        $this->notifySubscribers(new Event\ExecutionStartsEvent($statement));

        try {
            $result = parent::exec($statement);
        } catch (PDOException $e) {
            $this->notifySubscribers(new Event\ExecutionFailedEvent($e));
            throw $e;
        }

        if ($result !== false) {
            $this->notifySubscribers(new Event\ExecutionSucceededEvent($result));
        } else {

            $this->notifySubscribers(Event\ExecutionFailedEvent::fromError($this));
        }

        return $result;
    }

    /**
     * @phpstan-param array<PDO::ATTR_*, mixed> $options
     */
    #[\ReturnTypeWillChange]
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $nativeStatement = parent::prepare($query, $options);

        if ($nativeStatement !== false) {
            $this->notifySubscribers(new Event\PrepareEvent($query));

            return new PDOStatement($nativeStatement, $this);
        }

        return false;
    }

    #[\ReturnTypeWillChange]
    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false
    {
        $this->notifySubscribers(new Event\ExecutionStartsEvent($query));

        try {
            $nativeStatement = parent::query($query, $fetchMode, ...$args);
        } catch (PDOException $e) {
            $this->notifySubscribers(new Event\ExecutionFailedEvent($e));
            throw $e;
        }

        if ($nativeStatement !== false) {
            $wrappedStatement = new PDOStatement($nativeStatement, $this);
            $this->notifySubscribers(new Event\ExecutionSucceededEvent($wrappedStatement->rowCount()));

            return $wrappedStatement;
        } else {
            $this->notifySubscribers(Event\ExecutionFailedEvent::fromError($this));
        }

        return false;
    }

    public function rollBack(): bool
    {
        if ($result = parent::rollBack()) {
            $this->notifySubscribers(new Event\TransactionRollbackEvent);
        }

        return $result;
    }

    public function addSubscriber(Subscriber $subscriber): void
    {
        $this->subscribers[] = $subscriber;
    }

    public function notifySubscribers(Event $event): void
    {
        foreach ($this->subscribers as $subscriber) {
            $event->notifySubscriber($subscriber);
        }
    }
}
