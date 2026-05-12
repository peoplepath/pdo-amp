<?php

declare(strict_types=1);

namespace PeoplePath\PdoApm;

use Iterator;
use PDOException;

/**
 * Wrapper for native \PDOStatement that adds parameter tracking and event notification.
 */
class PDOStatement extends \PDOStatement
{
    private \PDOStatement $statement;

    private PDO $pdo;

    /**
     * @var array<string|int, mixed>
     */
    private array $boundParams = [];

    /**
     * Property hook to delegate queryString access to wrapped statement
     */
    public string $queryString {
        /** @phpstan-ignore propertyGetHook.noRead */
        get => $this->statement->queryString;
    }

    public function __construct(\PDOStatement $statement, PDO $pdo)
    {
        $this->statement = $statement;
        $this->pdo = $pdo;
    }

    public function getIterator(): Iterator
    {
        return $this->statement->getIterator();
    }

    /**
     * @param  mixed[]  $params
     */
    public function execute(?array $params = null): bool
    {
        // Merge bound params with execute params (execute params take precedence)
        // Use + operator to preserve integer keys (array_merge renumbers them)
        $allParams = ($params ?? []) + $this->boundParams;

        $this->pdo->notifySubscribers(new Event\ExecutionStartsEvent($this->statement->queryString));

        try {
            if ($result = $this->statement->execute($params)) {
                $this->pdo->notifySubscribers(new Event\ExecutionSucceededEvent($this->statement->rowCount(), $allParams));
            } else {
                $this->pdo->notifySubscribers(Event\ExecutionFailedEvent::fromError($this->pdo, $allParams));
            }
        } catch (PDOException $e) {
            $this->pdo->notifySubscribers(new Event\ExecutionFailedEvent($e, $allParams));
            throw $e;
        } finally {
            // Clear bound params for statement reuse
            $this->boundParams = [];
        }

        return $result;
    }

    public function bindValue(string|int $param, mixed $value, int $type = \PDO::PARAM_STR): bool
    {
        $result = $this->statement->bindValue($param, $value, $type);

        if ($result) {
            $this->boundParams[$param] = $value;
        }

        return $result;
    }

    public function bindParam(string|int $param, mixed &$var, int $type = \PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool
    {
        $result = $this->statement->bindParam($param, $var, $type, $maxLength, $driverOptions);

        if ($result) {
            // Store reference so changes to $var are reflected at execution time
            $this->boundParams[$param] = &$var;
        }

        return $result;
    }

    // Fetch methods
    public function fetch(int $mode = \PDO::FETCH_DEFAULT, int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->statement->fetch($mode, $cursorOrientation, $cursorOffset);
    }

    /**
     * @return array<mixed>
     */
    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        /** @phpstan-ignore-next-line */
        return $this->statement->fetchAll($mode, ...$args);
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->statement->fetchColumn($column);
    }

    /**
     * @param class-string<object>|null $class
     * @param array<mixed> $constructorArgs
     */
    public function fetchObject(?string $class = 'stdClass', array $constructorArgs = []): object|false
    {
        /** @phpstan-ignore-next-line */
        return $this->statement->fetchObject($class, $constructorArgs);
    }

    // Metadata methods
    public function rowCount(): int
    {
        return $this->statement->rowCount();
    }

    public function columnCount(): int
    {
        return $this->statement->columnCount();
    }

    // Cursor & mode methods
    public function closeCursor(): bool
    {
        return $this->statement->closeCursor();
    }

    public function setFetchMode(int $mode, mixed ...$args): true
    {
        /** @phpstan-ignore-next-line */
        return $this->statement->setFetchMode($mode, ...$args);
    }

    public function nextRowset(): bool
    {
        return $this->statement->nextRowset();
    }

    // Attribute methods
    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->statement->setAttribute($attribute, $value);
    }

    public function getAttribute(int $attribute): mixed
    {
        return $this->statement->getAttribute($attribute);
    }

    // Error methods
    public function errorCode(): ?string
    {
        return $this->statement->errorCode();
    }

    /**
     * @return array<mixed>
     */
    public function errorInfo(): array
    {
        return $this->statement->errorInfo();
    }

    // Debug & binding methods
    public function debugDumpParams(): ?bool
    {
        /** @phpstan-ignore-next-line */
        return $this->statement->debugDumpParams();
    }

    public function bindColumn(string|int $column, mixed &$var, int $type = \PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool
    {
        return $this->statement->bindColumn($column, $var, $type, $maxLength, $driverOptions);
    }
}
