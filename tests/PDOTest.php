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

        // Create a table with a NOT NULL constraint
        $pdo->exec('CREATE TABLE test_table (id INTEGER NOT NULL, value TEXT)');

        $pdo->addSubscriber($this->expectAction('Prepare'));
        $pdo->addSubscriber($this->expectAction('ExecutionStarts'));
        $pdo->addSubscriber($this->expectAction('ExecutionFailed'));

        $stmt = $pdo->prepare('INSERT INTO test_table (id, value) VALUES (?, ?)');
        $this->assertNotFalse($stmt);

        $this->expectException('PDOException');
        $this->expectExceptionMessage('NOT NULL constraint failed');

        // Try to insert NULL into NOT NULL column - this will throw during execute()
        $stmt->execute([null, 'test']);
    }

    public function test_statement_execute_failed_on_error(): void
    {
        $pdo = $this->createPDO(errmode: PDO::ERRMODE_SILENT);

        $stmt = $pdo->prepare('SELECT * FROM `not_a_table`');
        $this->assertFalse($stmt);
    }

    public function test_statement_get_iterator(): void
    {
        $pdo = $this->createPDO();

        // Create table and insert test data
        $pdo->exec('CREATE TABLE test_iterator (id INTEGER, name TEXT)');
        $pdo->exec("INSERT INTO test_iterator VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie')");

        $stmt = $pdo->prepare('SELECT * FROM test_iterator ORDER BY id');
        $this->assertNotFalse($stmt);
        $stmt->execute();

        // Use foreach to iterate (this calls getIterator())
        $results = [];
        foreach ($stmt as $row) {
            $results[] = $row;
        }

        $this->assertCount(3, $results);
        $this->assertSame(['id' => 1, 'name' => 'Alice'], $results[0]);
        $this->assertSame(['id' => 2, 'name' => 'Bob'], $results[1]);
        $this->assertSame(['id' => 3, 'name' => 'Charlie'], $results[2]);
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

    public function test_multiple_prepared_statements_executed_in_reverse_order(): void
    {
        $pdo = $this->createPDO();

        // Track the order of events
        $eventLog = [];

        $subscriber = $this->createMockForIntersectionOfInterfaces([
            Subscriber\PrepareSubscriber::class,
            Subscriber\ExecutionStartsSubscriber::class,
            Subscriber\ExecutionSucceededSubscriber::class,
        ]);
        $subscriber->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnCallback(function (Event\PrepareEvent $event) use (&$eventLog) {
                $eventLog[] = ['action' => 'prepare', 'query' => $event->query];
            });

        $subscriber->expects($this->exactly(2))
            ->method('executionStarts')
            ->willReturnCallback(function (Event\ExecutionStartsEvent $event) use (&$eventLog) {
                $eventLog[] = ['action' => 'executionStarts', 'query' => $event->query];
            });

        $subscriber->expects($this->exactly(2))
            ->method('executionSucceeded')
            ->willReturnCallback(function (Event\ExecutionSucceededEvent $event) use (&$eventLog) {
                $eventLog[] = ['action' => 'executionSucceeded', 'params' => $event->params];
            });

        /** @phpstan-ignore argument.type */
        $pdo->addSubscriber($subscriber);

        // Prepare two statements
        $query1 = 'SELECT ? AS first_value';
        $query2 = 'SELECT ? AS second_value';
        $stmt1 = $pdo->prepare($query1);
        $stmt2 = $pdo->prepare($query2);
        $this->assertNotFalse($stmt1);
        $this->assertNotFalse($stmt2);

        // Execute in reverse order
        $this->assertTrue($stmt2->execute([200]));
        $this->assertTrue($stmt1->execute([100]));

        $this->assertSame([
            [
                'action' => 'prepare',
                'query' => 'SELECT ? AS first_value',
            ],
            [
                'action' => 'prepare',
                'query' => 'SELECT ? AS second_value',
            ],
            [
                'action' => 'executionStarts',
                'query' => 'SELECT ? AS second_value',
            ],
            [
                'action' => 'executionSucceeded',
                'params' => [200],
            ],
            [
                'action' => 'executionStarts',
                'query' => 'SELECT ? AS first_value',
            ],
            [
                'action' => 'executionSucceeded',
                'params' => [100],
            ],
        ], $eventLog);
    }

    public function test_minimal_delay_after_prepare(): void
    {
        $pdo = $this->createPDO();
        $micro = microtime(true);

        $stmt = $pdo->prepare('SELECT USLEEP(10001)');
        $this->assertNotFalse($stmt);

        $this->assertLessThan(.01, microtime(true) - $micro);

        $stmt->execute();

        $this->assertGreaterThan(.01, microtime(true) - $micro);
    }

    public function test_bound_values_included_in_execution_succeeded_event(): void
    {
        $pdo = $this->createPDO();

        $capturedParams = null;
        $subscriber = $this->createMock(Subscriber\ExecutionSucceededSubscriber::class);
        $subscriber->expects($this->once())
            ->method('executionSucceeded')
            ->willReturnCallback(function (Event\ExecutionSucceededEvent $event) use (&$capturedParams) {
                $capturedParams = $event->params;
            });

        $pdo->addSubscriber($subscriber);
        $stmt = $pdo->prepare('SELECT :name, :age');
        $this->assertNotFalse($stmt);
        $stmt->bindValue(':name', 'Alice');
        $stmt->bindValue(':age', 30);
        $stmt->execute();

        $this->assertSame([':name' => 'Alice', ':age' => 30], $capturedParams);
    }

    public function test_bound_params_included_in_execution_succeeded_event(): void
    {
        $pdo = $this->createPDO();

        $capturedParams = null;
        $subscriber = $this->createMock(Subscriber\ExecutionSucceededSubscriber::class);
        $subscriber->expects($this->once())
            ->method('executionSucceeded')
            ->willReturnCallback(function (Event\ExecutionSucceededEvent $event) use (&$capturedParams) {
                $capturedParams = $event->params;
            });

        $pdo->addSubscriber($subscriber);
        $name = 'Bob';
        $age = 25;
        $stmt = $pdo->prepare('SELECT :name, :age');
        $this->assertNotFalse($stmt);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':age', $age);
        $stmt->execute();

        // bindParam without type defaults to PARAM_STR, so values are stringified
        $this->assertSame([':name' => 'Bob', ':age' => '25'], $capturedParams);
    }

    public function test_bound_params_reflect_variable_changes(): void
    {
        $pdo = $this->createPDO();

        $capturedParams = null;
        $subscriber = $this->createMock(Subscriber\ExecutionSucceededSubscriber::class);
        $subscriber->expects($this->once())
            ->method('executionSucceeded')
            ->willReturnCallback(function (Event\ExecutionSucceededEvent $event) use (&$capturedParams) {
                $capturedParams = $event->params;
            });

        $pdo->addSubscriber($subscriber);
        $value = 100;
        $stmt = $pdo->prepare('SELECT :value');
        $this->assertNotFalse($stmt);
        $stmt->bindParam(':value', $value);

        // Change the variable after binding - bindParam uses references
        $value = 200;
        $stmt->execute();

        // Should reflect the updated value at execution time
        // bindParam without type defaults to PARAM_STR, so values are stringified
        $this->assertSame([':value' => '200'], $capturedParams);
    }

    public function test_mixed_bound_and_execute_parameters(): void
    {
        $pdo = $this->createPDO();

        $capturedParams = null;
        $subscriber = $this->createMock(Subscriber\ExecutionSucceededSubscriber::class);
        $subscriber->expects($this->once())
            ->method('executionSucceeded')
            ->willReturnCallback(function (Event\ExecutionSucceededEvent $event) use (&$capturedParams) {
                $capturedParams = $event->params;
            });

        $pdo->addSubscriber($subscriber);
        $stmt = $pdo->prepare('SELECT :name, :age');
        $this->assertNotFalse($stmt);
        $stmt->bindValue(':name', 'Alice');
        $stmt->execute([':age' => 30]);

        // Both bound and execute params should be present
        $this->assertSame([':age' => 30, ':name' => 'Alice'], $capturedParams);
    }

    public function test_execute_params_override_bound_params(): void
    {
        $pdo = $this->createPDO();

        $capturedParams = null;
        $subscriber = $this->createMock(Subscriber\ExecutionSucceededSubscriber::class);
        $subscriber->expects($this->once())
            ->method('executionSucceeded')
            ->willReturnCallback(function (Event\ExecutionSucceededEvent $event) use (&$capturedParams) {
                $capturedParams = $event->params;
            });

        $pdo->addSubscriber($subscriber);
        $stmt = $pdo->prepare('SELECT :name');
        $this->assertNotFalse($stmt);
        $stmt->bindValue(':name', 'Alice');
        $stmt->execute([':name' => 'Bob']);

        // Execute params should take precedence
        $this->assertSame([':name' => 'Bob'], $capturedParams);
    }

    public function test_bound_params_cleared_after_execution(): void
    {
        $pdo = $this->createPDO();

        $capturedParams = [];
        $subscriber = $this->createMock(Subscriber\ExecutionSucceededSubscriber::class);
        $subscriber->expects($this->exactly(2))
            ->method('executionSucceeded')
            ->willReturnCallback(function (Event\ExecutionSucceededEvent $event) use (&$capturedParams) {
                $capturedParams[] = $event->params;
            });

        $pdo->addSubscriber($subscriber);
        $stmt = $pdo->prepare('SELECT :value');
        $this->assertNotFalse($stmt);

        // First execution with bound param
        $stmt->bindValue(':value', 100);
        $stmt->execute();

        // Second execution without binding - should not see previous param
        $stmt->execute([':value' => 200]);

        $this->assertSame([
            [':value' => 100],  // First execution
            [':value' => 200],  // Second execution - only has the execute param
        ], $capturedParams);
    }

    public function test_bound_values_included_in_execution_failed_event(): void
    {
        $pdo = $this->createPDO(errmode: PDO::ERRMODE_SILENT);

        $capturedParams = null;
        $subscriber = $this->createMock(Subscriber\ExecutionFailedSubscriber::class);
        $subscriber->expects($this->once())
            ->method('executionFailed')
            ->willReturnCallback(function (Event\ExecutionFailedEvent $event) use (&$capturedParams) {
                $capturedParams = $event->params;
            });

        $pdo->addSubscriber($subscriber);

        // Create a table with a unique constraint
        $pdo->exec('CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT UNIQUE)');
        $pdo->exec("INSERT INTO test_users (id, name) VALUES (1, 'Alice')");

        // Try to insert duplicate name - will fail due to UNIQUE constraint
        $stmt = $pdo->prepare('INSERT INTO test_users (id, name) VALUES (:id, :name)');
        $this->assertNotFalse($stmt);
        $stmt->bindValue(':id', 2);
        $stmt->bindValue(':name', 'Alice');  // Duplicate name
        $result = $stmt->execute();

        // Execute should fail and params should be captured in the failed event
        $this->assertFalse($result);
        $this->assertSame([':id' => 2, ':name' => 'Alice'], $capturedParams);
    }

    public function test_bound_values_with_positional_parameters(): void
    {
        $pdo = $this->createPDO();

        $capturedParams = null;
        $subscriber = $this->createMock(Subscriber\ExecutionSucceededSubscriber::class);
        $subscriber->expects($this->once())
            ->method('executionSucceeded')
            ->willReturnCallback(function (Event\ExecutionSucceededEvent $event) use (&$capturedParams) {
                $capturedParams = $event->params;
            });

        $pdo->addSubscriber($subscriber);
        $stmt = $pdo->prepare('SELECT ?, ?');
        $this->assertNotFalse($stmt);
        $stmt->bindValue(1, 'Alice');
        $stmt->bindValue(2, 30);
        $stmt->execute();

        // bindValue uses 1-based indexing for positional params
        $this->assertSame([1 => 'Alice', 2 => 30], $capturedParams);
    }

    public function test_bound_params_with_positional_parameters(): void
    {
        $pdo = $this->createPDO();

        $capturedParams = null;
        $subscriber = $this->createMock(Subscriber\ExecutionSucceededSubscriber::class);
        $subscriber->expects($this->once())
            ->method('executionSucceeded')
            ->willReturnCallback(function (Event\ExecutionSucceededEvent $event) use (&$capturedParams) {
                $capturedParams = $event->params;
            });

        $pdo->addSubscriber($subscriber);
        $name = 'Bob';
        $age = 25;
        $stmt = $pdo->prepare('SELECT ?, ?');
        $this->assertNotFalse($stmt);
        $stmt->bindParam(1, $name);
        $stmt->bindParam(2, $age);
        $stmt->execute();

        // bindParam without type defaults to PARAM_STR, so values are stringified
        $this->assertSame([1 => 'Bob', 2 => '25'], $capturedParams);
    }

    public function test_positional_bound_params_reflect_variable_changes(): void
    {
        $pdo = $this->createPDO();

        $capturedParams = null;
        $subscriber = $this->createMock(Subscriber\ExecutionSucceededSubscriber::class);
        $subscriber->expects($this->once())
            ->method('executionSucceeded')
            ->willReturnCallback(function (Event\ExecutionSucceededEvent $event) use (&$capturedParams) {
                $capturedParams = $event->params;
            });

        $pdo->addSubscriber($subscriber);
        $value1 = 100;
        $value2 = 200;
        $stmt = $pdo->prepare('SELECT ?, ?');
        $this->assertNotFalse($stmt);
        $stmt->bindParam(1, $value1);
        $stmt->bindParam(2, $value2);

        // Change variables after binding - bindParam uses references
        $value1 = 300;
        $value2 = 400;
        $stmt->execute();

        // Should reflect the updated values at execution time
        // bindParam without type defaults to PARAM_STR, so values are stringified
        $this->assertSame([1 => '300', 2 => '400'], $capturedParams);
    }

    public function test_mixed_positional_bound_and_execute_parameters(): void
    {
        $pdo = $this->createPDO();

        $capturedParams = null;
        $subscriber = $this->createMock(Subscriber\ExecutionSucceededSubscriber::class);
        $subscriber->expects($this->once())
            ->method('executionSucceeded')
            ->willReturnCallback(function (Event\ExecutionSucceededEvent $event) use (&$capturedParams) {
                $capturedParams = $event->params;
            });

        $pdo->addSubscriber($subscriber);
        $stmt = $pdo->prepare('SELECT ?, ?');
        $this->assertNotFalse($stmt);
        $stmt->bindValue(1, 'Alice');
        $stmt->bindValue(2, 'Bob');
        $stmt->execute([1 => 'Charlie']);  // Override second param

        // Execute params override bound params for the same key
        $this->assertSame([1 => 'Charlie', 2 => 'Bob'], $capturedParams);
    }

    public function test_execute_params_override_bound_positional_params(): void
    {
        $pdo = $this->createPDO();

        $capturedParams = null;
        $subscriber = $this->createMock(Subscriber\ExecutionSucceededSubscriber::class);
        $subscriber->expects($this->once())
            ->method('executionSucceeded')
            ->willReturnCallback(function (Event\ExecutionSucceededEvent $event) use (&$capturedParams) {
                $capturedParams = $event->params;
            });

        $pdo->addSubscriber($subscriber);
        $stmt = $pdo->prepare('SELECT ?');
        $this->assertNotFalse($stmt);
        $stmt->bindValue(1, 'Alice');
        $stmt->execute(['Bob']);  // 0-based array for execute()

        // Both params present: bound param at key 1, execute param at key 0
        // In practice, PDO will use the execute param since it's position 0 (first ?)
        $this->assertSame([0 => 'Bob', 1 => 'Alice'], $capturedParams);
    }

    public function test_positional_bound_params_cleared_after_execution(): void
    {
        $pdo = $this->createPDO();

        $capturedParams = [];
        $subscriber = $this->createMock(Subscriber\ExecutionSucceededSubscriber::class);
        $subscriber->expects($this->exactly(2))
            ->method('executionSucceeded')
            ->willReturnCallback(function (Event\ExecutionSucceededEvent $event) use (&$capturedParams) {
                $capturedParams[] = $event->params;
            });

        $pdo->addSubscriber($subscriber);
        $stmt = $pdo->prepare('SELECT ?');
        $this->assertNotFalse($stmt);

        // First execution with bound param
        $stmt->bindValue(1, 100);
        $stmt->execute();

        // Second execution without binding - should not see previous param
        $stmt->execute([200]);  // 0-based array for execute()

        $this->assertSame([
            [1 => 100],  // First execution - bound param at position 1
            [0 => 200],  // Second execution - execute param at position 0
        ], $capturedParams);
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
