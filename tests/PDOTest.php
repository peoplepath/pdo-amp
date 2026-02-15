<?php

declare(strict_types=1);

namespace PeoplePath\PdoApm;

use PHPUnit\Framework\TestCase;

use function lcfirst;
use function sprintf;

/**
 * Tests class PeoplePath\PdoApm\PDO
 */
final class PDOTest extends TestCase
{
    public function test_exec_creates_events(): void
    {
        $pdo = $this->createPDO();

        $pdo->addSubscriber($this->expectAction('ExecutionStarts'));
        $pdo->addSubscriber($this->expectAction('ExecutionSucceeded'));

        $this->assertSame(0, $pdo->exec('SELECT USLEEP(1)'));
    }

    public function test_exec_creates_failed_event_on_exception(): void
    {
        $pdo = $this->createPDO();

        $pdo->addSubscriber($this->expectAction('ExecutionStarts'));
        $pdo->addSubscriber($this->expectAction('ExecutionFailed'));

        $this->expectException('PDOException');
        $this->expectExceptionMessage('SQLSTATE[HY000]: General error: 1 no such table: not_a_table');
        $pdo->exec('SELECT * FROM `not_a_table`');
    }

    public function test_exec_creates_failed_event_on_error(): void
    {
        $pdo = $this->createPDO(errmode: PDO::ERRMODE_SILENT);

        $pdo->addSubscriber($this->expectAction('ExecutionStarts'));
        $pdo->addSubscriber($this->expectAction('ExecutionFailed'));

        $this->assertFalse($pdo->exec('SELECT * FROM `not_a_table`'));
    }

    public function test_prepare_returns_pdo_statement(): void
    {
        $pdo = $this->createPDO();

        $pdo->addSubscriber($this->expectAction('Prepare'));
        $pdo->addSubscriber($this->expectAction('ExecutionStarts'));
        $pdo->addSubscriber($this->expectAction('ExecutionSucceeded'));

        $this->assertInstanceOf(PDOStatement::class, $stmt = $pdo->prepare('SELECT USLEEP(?)'));
        $stmt->execute([1]);
    }

    public function test_query_creates_events(): void
    {
        $pdo = $this->createPDO();

        $pdo->addSubscriber($this->expectAction('ExecutionStarts'));
        $pdo->addSubscriber($this->expectAction('ExecutionSucceeded'));

        $this->assertInstanceOf(PDOStatement::class, $stmt = $pdo->query('SELECT USLEEP(1) AS `sleep`, 123 AS `value`'));
        $this->assertSame([
            'sleep' => null,
            'value' => 123,
        ], $stmt->fetch());
    }

    public function test_query_creates_event_on_excetion(): void
    {
        $pdo = $this->createPDO();

        $pdo->addSubscriber($this->expectAction('ExecutionStarts'));
        $pdo->addSubscriber($this->expectAction('ExecutionFailed'));

        $this->expectException('PDOException');
        $pdo->query('SELECT * FROM `not_a_table`');
    }

    public function test_query_creates_event_on_error(): void
    {
        $pdo = $this->createPDO(errmode: PDO::ERRMODE_SILENT);

        $pdo->addSubscriber($this->expectAction('ExecutionStarts'));
        $pdo->addSubscriber($this->expectAction('ExecutionFailed'));

        $this->assertFalse($pdo->query('SELECT * FROM `not_a_table`'));
    }

    public function test_begin_transaction(): void
    {
        $pdo = $this->createPDO();

        $pdo->addSubscriber($this->expectAction('TransactionBegin'));

        $this->assertTrue($pdo->beginTransaction());
    }

    public function test_begin_transaction_fail_on_exception(): void
    {
        $pdo = $this->createPDO();

        $pdo->addSubscriber($this->expectAction('TransactionBegin'));

        $this->assertTrue($pdo->beginTransaction());

        $this->expectException('PDOException');
        $this->expectExceptionMessage('There is already an active transaction');
        $pdo->beginTransaction();
    }

    public function test_commit_transaction(): void
    {
        $pdo = $this->createPDO();

        $pdo->addSubscriber($this->expectAction('TransactionBegin'));
        $pdo->addSubscriber($this->expectAction('TransactionCommit'));

        $this->assertTrue($pdo->beginTransaction());
        $this->assertTrue($pdo->commit());
    }

    public function test_rollback_transaction(): void
    {
        $pdo = $this->createPDO();

        $pdo->addSubscriber($this->expectAction('TransactionBegin'));
        $pdo->addSubscriber($this->expectAction('TransactionRollback'));

        $this->assertTrue($pdo->beginTransaction());
        $this->assertTrue($pdo->rollBack());
    }

    public function test_statement_execute_with_parameters(): void
    {
        $pdo = $this->createPDO();

        $pdo->addSubscriber($this->expectAction('Prepare'));
        $pdo->addSubscriber($this->expectAction('ExecutionStarts'));
        $pdo->addSubscriber($this->expectAction('ExecutionSucceeded'));

        $stmt = $pdo->prepare('SELECT ? AS value');
        $this->assertNotFalse($stmt);
        $this->assertTrue($stmt->execute([123]));
        $this->assertSame(['value' => '123'], $stmt->fetch());
    }

    public function test_statement_execute_failed_on_exception(): void
    {
        $pdo = $this->createPDO();

        $this->expectException('PDOException');
        $pdo->prepare('SELECT * FROM `not_a_table`');
    }

    public function test_statement_execute_failed_on_error(): void
    {
        $pdo = $this->createPDO(errmode: PDO::ERRMODE_SILENT);

        $stmt = $pdo->prepare('SELECT * FROM `not_a_table`');
        $this->assertFalse($stmt);
    }

    public function test_execution_starts_event_contains_query(): void
    {
        $pdo = $this->createPDO();

        $query = 'SELECT 1 AS value';
        $subscriber = $this->createMock(Subscriber\ExecutionStartsSubscriber::class);
        $subscriber->expects($this->once())
            ->method('executionStarts')
            ->with($this->callback(function (Event\ExecutionStartsEvent $event) use ($query) {
                return $event->query === $query;
            }));

        $pdo->addSubscriber($subscriber);
        $pdo->exec($query);
    }

    public function test_multiple_subscribers_are_notified(): void
    {
        $pdo = $this->createPDO();

        $subscriber1 = $this->expectAction('ExecutionStarts');
        $subscriber2 = $this->expectAction('ExecutionStarts');
        $subscriber3 = $this->expectAction('ExecutionSucceeded');

        $pdo->addSubscriber($subscriber1);
        $pdo->addSubscriber($subscriber2);
        $pdo->addSubscriber($subscriber3);

        $pdo->exec('SELECT 1');
    }

    public function test_prepare_event_contains_query(): void
    {
        $pdo = $this->createPDO();

        $query = 'SELECT ? AS value';
        $subscriber = $this->createMock(Subscriber\PrepareSubscriber::class);
        $subscriber->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function (Event\PrepareEvent $event) use ($query) {
                return $event->query === $query;
            }));

        $pdo->addSubscriber($subscriber);
        $pdo->prepare($query);
    }

    /**
     * @phpstan-param PDO::ERRMODE_* $errmode
     * @phpstan-param PDO::FETCH_*   $fetchmode
     */
    private function createPDO(int $errmode = PDO::ERRMODE_EXCEPTION, int $fetchmode = PDO::FETCH_ASSOC): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        @$pdo->sqliteCreateFunction('USLEEP', usleep(...), 1);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, $errmode);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }

    /**
     * @phpstan-param non-empty-string $action
     */
    private function expectAction(string $action): Subscriber
    {
        /** @var class-string<Subscriber> */
        $subscriberClass = sprintf('%s\Subscriber\%sSubscriber', __NAMESPACE__, $action);

        /** @var class-string<Event> */
        $eventClass = sprintf('%s\Event\%sEvent', __NAMESPACE__, $action);

        $subscriber = $this->createMock($subscriberClass);
        $subscriber->expects($this->once())
            ->method(lcfirst($action))
            ->with($this->isInstanceOf($eventClass));

        return $subscriber;
    }
}
