<?php

declare(strict_types=1);

namespace PeoplePath\PdoApm;

use PHPUnit\Framework\TestCase;

use function lcfirst;
use function sprintf;

/**
 * Tests persistent connection support for PDO APM
 */
final class PersistentConnectionTest extends TestCase
{
    public function test_persistent_connection_can_be_created(): void
    {
        $pdo = $this->createPersistentPDO();
        $this->assertInstanceOf(PDO::class, $pdo);
    }

    public function test_persistent_connection_prepare_returns_wrapped_statement(): void
    {
        $pdo = $this->createPersistentPDO();

        $stmt = $pdo->prepare('SELECT ? AS value');
        $this->assertNotFalse($stmt);
        $this->assertInstanceOf(PDOStatement::class, $stmt);
    }

    public function test_persistent_connection_query_returns_wrapped_statement(): void
    {
        $pdo = $this->createPersistentPDO();

        $stmt = $pdo->query('SELECT 123 AS value');
        $this->assertNotFalse($stmt);
        $this->assertInstanceOf(PDOStatement::class, $stmt);
    }

    public function test_persistent_connection_fires_execution_starts_event(): void
    {
        $pdo = $this->createPersistentPDO();
        $pdo->addSubscriber($this->expectAction('ExecutionStarts'));

        $stmt = $pdo->prepare('SELECT ? AS value');
        $this->assertNotFalse($stmt);
        $stmt->execute([123]);
    }

    public function test_persistent_connection_fires_execution_succeeded_event(): void
    {
        $pdo = $this->createPersistentPDO();
        $pdo->addSubscriber($this->expectAction('ExecutionSucceeded'));

        $stmt = $pdo->prepare('SELECT ? AS value');
        $this->assertNotFalse($stmt);
        $stmt->execute([123]);
    }

    public function test_persistent_connection_fires_execution_failed_event(): void
    {
        $pdo = $this->createPersistentPDO(errmode: PDO::ERRMODE_SILENT);

        $capturedEvent = false;
        $subscriber = $this->createMock(Subscriber\ExecutionFailedSubscriber::class);
        $subscriber->expects($this->once())
            ->method('executionFailed')
            ->willReturnCallback(function (Event\ExecutionFailedEvent $event) use (&$capturedEvent) {
                $capturedEvent = true;
            });

        $pdo->addSubscriber($subscriber);

        // Create a table with a unique constraint
        $pdo->exec('CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT UNIQUE)');
        $pdo->exec("INSERT INTO test_users (id, name) VALUES (1, 'Alice')");

        // Try to insert duplicate name - will fail due to UNIQUE constraint
        $stmt = $pdo->prepare('INSERT INTO test_users (id, name) VALUES (?, ?)');
        $this->assertNotFalse($stmt);
        $stmt->bindValue(1, 2);
        $stmt->bindValue(2, 'Alice');  // Duplicate name
        $result = $stmt->execute();

        // Execute should fail and event should be fired
        $this->assertFalse($result);
        $this->assertTrue($capturedEvent);
    }

    public function test_persistent_connection_fires_prepare_event(): void
    {
        $pdo = $this->createPersistentPDO();
        $pdo->addSubscriber($this->expectAction('Prepare'));

        $stmt = $pdo->prepare('SELECT ? AS value');
        $this->assertNotFalse($stmt);
    }

    public function test_persistent_connection_fires_transaction_begin_event(): void
    {
        $pdo = $this->createPersistentPDO();
        $pdo->addSubscriber($this->expectAction('TransactionBegin'));

        $this->assertTrue($pdo->beginTransaction());
    }

    public function test_persistent_connection_fires_transaction_commit_event(): void
    {
        $pdo = $this->createPersistentPDO();
        $pdo->addSubscriber($this->expectAction('TransactionBegin'));
        $pdo->addSubscriber($this->expectAction('TransactionCommit'));

        $this->assertTrue($pdo->beginTransaction());
        $this->assertTrue($pdo->commit());
    }

    public function test_persistent_connection_fires_transaction_rollback_event(): void
    {
        $pdo = $this->createPersistentPDO();
        $pdo->addSubscriber($this->expectAction('TransactionBegin'));
        $pdo->addSubscriber($this->expectAction('TransactionRollback'));

        $this->assertTrue($pdo->beginTransaction());
        $this->assertTrue($pdo->rollBack());
    }

    public function test_persistent_connection_tracks_bound_values(): void
    {
        $pdo = $this->createPersistentPDO();

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

    public function test_persistent_connection_tracks_bound_params(): void
    {
        $pdo = $this->createPersistentPDO();

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

    public function test_persistent_connection_bound_params_reflect_variable_changes(): void
    {
        $pdo = $this->createPersistentPDO();

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

    public function test_persistent_connection_execute_params_override_bound_params(): void
    {
        $pdo = $this->createPersistentPDO();

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

    public function test_persistent_connection_bound_params_cleared_after_execution(): void
    {
        $pdo = $this->createPersistentPDO();

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

    public function test_persistent_connection_statement_reuse(): void
    {
        $pdo = $this->createPersistentPDO();

        $capturedParams = [];
        $subscriber = $this->createMock(Subscriber\ExecutionSucceededSubscriber::class);
        $subscriber->expects($this->exactly(3))
            ->method('executionSucceeded')
            ->willReturnCallback(function (Event\ExecutionSucceededEvent $event) use (&$capturedParams) {
                $capturedParams[] = $event->params;
            });

        $pdo->addSubscriber($subscriber);
        $stmt = $pdo->prepare('SELECT :value');
        $this->assertNotFalse($stmt);

        // Execute the same statement multiple times with different parameters
        $stmt->execute([':value' => 100]);
        $stmt->execute([':value' => 200]);
        $stmt->execute([':value' => 300]);

        $this->assertSame([
            [':value' => 100],
            [':value' => 200],
            [':value' => 300],
        ], $capturedParams);
    }

    public function test_persistent_connection_wrapper_delegates_fetch(): void
    {
        $pdo = $this->createPersistentPDO();

        $stmt = $pdo->query('SELECT 123 AS value');
        $this->assertNotFalse($stmt);
        $this->assertSame(['value' => 123], $stmt->fetch());
    }

    public function test_persistent_connection_wrapper_delegates_fetch_all(): void
    {
        $pdo = $this->createPersistentPDO();

        $stmt = $pdo->query('SELECT 123 AS value UNION SELECT 456 AS value');
        $this->assertNotFalse($stmt);
        $this->assertSame([
            ['value' => 123],
            ['value' => 456],
        ], $stmt->fetchAll());
    }

    public function test_persistent_connection_wrapper_delegates_row_count(): void
    {
        $pdo = $this->createPersistentPDO();

        $pdo->exec('CREATE TABLE test (id INT)');
        $pdo->exec('INSERT INTO test VALUES (1), (2), (3)');

        $stmt = $pdo->query('SELECT * FROM test');
        $this->assertNotFalse($stmt);
        // Test that rowCount method works without error
        $rowCount = $stmt->rowCount();
        $this->assertGreaterThanOrEqual(0, $rowCount);
    }

    public function test_persistent_connection_wrapper_delegates_column_count(): void
    {
        $pdo = $this->createPersistentPDO();

        $stmt = $pdo->query('SELECT 1 AS a, 2 AS b, 3 AS c');
        $this->assertNotFalse($stmt);
        $this->assertSame(3, $stmt->columnCount());
    }

    public function test_persistent_connection_multiple_statements_executed_in_reverse_order(): void
    {
        $pdo = $this->createPersistentPDO();

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

    /**
     * @phpstan-param PDO::ERRMODE_* $errmode
     */
    private function createPersistentPDO(int $errmode = PDO::ERRMODE_EXCEPTION): PDO
    {
        $pdo = new PDO('sqlite::memory:', options: [
            PDO::ATTR_PERSISTENT => true,
        ]);
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
