<?php

declare(strict_types=1);

namespace PeoplePath\PdoApm;

use Iterator;
use IteratorAggregate;
use PDOException;

/**
 * @implements IteratorAggregate<int, mixed>
 *
 * @method mixed fetch(int $mode = \PDO::FETCH_DEFAULT, int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $cursorOffset = 0)
 * @method array<mixed> fetchAll(int $mode = \PDO::FETCH_DEFAULT, mixed ...$args)
 * @method int rowCount()
 * @method int columnCount()
 * @method bool closeCursor()
 * @method mixed fetchColumn(int $column = 0)
 * @method bool setFetchMode(int $mode, mixed ...$args)
 * @method object|false fetchObject(?string $class = 'stdClass', array<mixed> $constructorArgs = [])
 * @method bool setAttribute(int $attribute, mixed $value)
 * @method mixed getAttribute(int $attribute)
 * @method ?string errorCode()
 * @method array<mixed> errorInfo()
 * @method bool nextRowset()
 * @method void debugDumpParams()
 * @method bool bindColumn(string|int $column, mixed &$var, int $type = \PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null)
 */
class PDOStatement implements IteratorAggregate
{
    private \PDOStatement $statement;

    private PDO $pdo;

    /**
     * @var array<string|int, mixed>
     */
    private array $boundParams = [];

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

    /**
     * @param  array<mixed>  $args
     */
    public function __call(string $method, array $args): mixed
    {
        return $this->statement->$method(...$args);
    }

    public function __get(string $name): mixed
    {
        return $this->statement->$name;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->statement->$name = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->statement->$name);
    }
}
