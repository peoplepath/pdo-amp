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
        $this->setAttribute(self::ATTR_STATEMENT_CLASS, [PDOStatement::class]);
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

    /** @phpstan-param array<PDO::ATTR_*, mixed> $options */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        /** @var PDOStatement|false */
        $statement = parent::prepare($query, $options);

        if ($statement !== false) {
            $statement->setPDO($this);
            $this->notifySubscribers(new Event\PrepareEvent($query));
        }

        return $statement;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false
    {
        $this->notifySubscribers(new Event\ExecutionStartsEvent($query));

        try {
            /** @var PDOStatement|false */
            $statement = parent::query($query, $fetchMode, ...$args);
        } catch (PDOException $e) {
            $this->notifySubscribers(new Event\ExecutionFailedEvent($e));
            throw $e;
        }

        if ($statement !== false) {
            $statement->setPDO($this);
            $this->notifySubscribers(new Event\ExecutionSucceededEvent($statement->rowCount()));
        } else {
            $this->notifySubscribers(Event\ExecutionFailedEvent::fromError($this));
        }

        return $statement;
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
