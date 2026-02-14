<?php

declare(strict_types=1);

namespace PeoplePath\PdoApm;

use PDOException;

class PDOStatement extends \PDOStatement
{
    private PDO $pdo;

    /**
     * @param  mixed[]  $params
     */
    public function execute(?array $params = null): bool
    {
        $this->pdo->notifySubscribers(new Event\ExecutionStartsEvent($this->queryString));

        try {
            if ($result = parent::execute($params)) {
                $this->pdo->notifySubscribers(new Event\ExecutionSucceededEvent($this->rowCount(), $params));
            } else {
                $this->pdo->notifySubscribers(Event\ExecutionFailedEvent::fromError($this->pdo, $params));
            }
        } catch (PDOException $e) {
            $this->pdo->notifySubscribers(new Event\ExecutionFailedEvent($e, $params));
            throw $e;
        }

        return $result;
    }

    public function setPDO(PDO $pdo): void
    {
        $this->pdo = $pdo;
    }
}
