<?php

declare(strict_types=1);

namespace PeoplePath\PdoApm;

use PDOException;

class PDOStatement extends \PDOStatement
{
    private PDO $pdo;

    /**
     * @var array<string|int, mixed>
     */
    private array $boundParams = [];

    /**
     * @param  mixed[]  $params
     */
    public function execute(?array $params = null): bool
    {
        // Merge bound params with execute params (execute params take precedence)
        // Use + operator to preserve integer keys (array_merge renumbers them)
        $allParams = ($params ?? []) + $this->boundParams;

        $this->pdo->notifySubscribers(new Event\ExecutionStartsEvent($this->queryString));

        try {
            if ($result = parent::execute($params)) {
                $this->pdo->notifySubscribers(new Event\ExecutionSucceededEvent($this->rowCount(), $allParams));
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
        $result = parent::bindValue($param, $value, $type);

        if ($result) {
            $this->boundParams[$param] = $value;
        }

        return $result;
    }

    public function bindParam(string|int $param, mixed &$var, int $type = \PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool
    {
        $result = parent::bindParam($param, $var, $type, $maxLength, $driverOptions);

        if ($result) {
            // Store reference so changes to $var are reflected at execution time
            $this->boundParams[$param] = &$var;
        }

        return $result;
    }

    public function setPDO(PDO $pdo): void
    {
        $this->pdo = $pdo;
    }
}
