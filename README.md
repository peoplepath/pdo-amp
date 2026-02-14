# PDO APM

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

A simple, lightweight Application Performance Monitoring (APM) library for PDO that provides detailed insights into your database operations through an event-driven architecture.

## Features

- 🔍 **Query Profiling** - Track execution time, row counts, and parameters for all database operations
- 📊 **Event-Driven Architecture** - Subscribe to specific database events using a clean observer pattern
- 🎯 **Zero Configuration** - Drop-in replacement for standard PDO with no configuration required
- 🚀 **Lightweight** - Minimal overhead with no external dependencies
- 🔄 **Transaction Tracking** - Monitor transaction lifecycle (begin, commit, rollback)
- ⚠️ **Error Tracking** - Capture failed queries with full context including parameters
- 🧪 **Fully Tested** - Comprehensive test suite with 100% coverage of core functionality

## Installation

Install via Composer:

```bash
composer require peoplepath/pdo-apm
```

## Requirements

- PHP >= 8.2
- PDO extension

## Quick Start

```php
<?php

use PeoplePath\PdoApm\PDO;
use PeoplePath\PdoApm\Event;
use PeoplePath\PdoApm\Subscriber;

// Create PDO instance (drop-in replacement for standard PDO)
$pdo = new PDO('mysql:host=localhost;dbname=mydb', 'user', 'pass');

// Create and attach a subscriber
$profiler = new class implements
    Subscriber\ExecutionStartsSubscriber,
    Subscriber\ExecutionSucceededSubscriber
{
    private float $startTime;

    public function executionStarts(Event\ExecutionStartsEvent $event): void {
        $this->startTime = microtime(true);
        echo "Executing: {$event->query}\n";
    }

    public function executionSucceeded(Event\ExecutionSucceededEvent $event): void {
        $duration = microtime(true) - $this->startTime;
        echo "Completed in {$duration}s, {$event->rowCount} rows affected\n";
    }
};

$pdo->addSubscriber($profiler);

// Use PDO normally - all operations are automatically tracked
$pdo->exec('CREATE TABLE users (id INT, name VARCHAR(255))');
$stmt = $pdo->prepare('INSERT INTO users VALUES (?, ?)');
$stmt->execute([1, 'Alice']);
```

## Events

PDO APM emits the following events during database operations:

### Query Execution Events

| Event | Triggered When | Properties |
|-------|---------------|------------|
| `ExecutionStartsEvent` | Query execution begins | `query` |
| `ExecutionSucceededEvent` | Query completes successfully | `rowCount`, `params` |
| `ExecutionFailedEvent` | Query fails | `exception`, `params` |
| `PrepareEvent` | Statement is prepared | `query` |

### Transaction Events

| Event | Triggered When | Properties |
|-------|---------------|------------|
| `TransactionBeginEvent` | Transaction starts | - |
| `TransactionCommitEvent` | Transaction commits | - |
| `TransactionRollbackEvent` | Transaction rolls back | - |

## Subscribers

To receive events, create a subscriber by implementing one or more subscriber interfaces:

```php
use PeoplePath\PdoApm\Subscriber;
use PeoplePath\PdoApm\Event;

class MySubscriber implements
    Subscriber\ExecutionStartsSubscriber,
    Subscriber\ExecutionSucceededSubscriber,
    Subscriber\ExecutionFailedSubscriber,
    Subscriber\PrepareSubscriber,
    Subscriber\TransactionBeginSubscriber,
    Subscriber\TransactionCommitSubscriber,
    Subscriber\TransactionRollbackSubscriber
{
    public function executionStarts(Event\ExecutionStartsEvent $event): void {
        // Called when query execution starts
    }

    public function executionSucceeded(Event\ExecutionSucceededEvent $event): void {
        // Called when query succeeds
        // Access: $event->rowCount, $event->params
    }

    public function executionFailed(Event\ExecutionFailedEvent $event): void {
        // Called when query fails
        // Access: $event->exception, $event->params
    }

    public function prepare(Event\PrepareEvent $event): void {
        // Called when statement is prepared
        // Access: $event->query
    }

    public function transactionBegin(Event\TransactionBeginEvent $event): void {
        // Called when transaction begins
    }

    public function transactionCommit(Event\TransactionCommitEvent $event): void {
        // Called when transaction commits
    }

    public function transactionRollback(Event\TransactionRollbackEvent $event): void {
        // Called when transaction rolls back
    }
}
```

You only need to implement the subscriber interfaces for events you're interested in.

## Usage Examples

### Basic Query Profiler

```php
use PeoplePath\PdoApm\PDO;
use PeoplePath\PdoApm\Event;
use PeoplePath\PdoApm\Subscriber;

class QueryLogger implements
    Subscriber\ExecutionStartsSubscriber,
    Subscriber\ExecutionSucceededSubscriber
{
    private array $queries = [];
    private ?float $startTime = null;

    public function executionStarts(Event\ExecutionStartsEvent $event): void {
        $this->startTime = microtime(true);
    }

    public function executionSucceeded(Event\ExecutionSucceededEvent $event): void {
        $this->queries[] = [
            'duration' => microtime(true) - $this->startTime,
            'rows' => $event->rowCount,
            'params' => $event->params,
        ];
    }

    public function getQueries(): array {
        return $this->queries;
    }
}

$pdo = new PDO('sqlite::memory:');
$logger = new QueryLogger();
$pdo->addSubscriber($logger);

// Execute queries
$pdo->exec('CREATE TABLE test (id INT)');
$stmt = $pdo->prepare('INSERT INTO test VALUES (?)');
$stmt->execute([1]);

// Review profiling data
print_r($logger->getQueries());
```

### Slow Query Detector

```php
use PeoplePath\PdoApm\PDO;
use PeoplePath\PdoApm\Event;
use PeoplePath\PdoApm\Subscriber;

class SlowQueryDetector implements
    Subscriber\ExecutionStartsSubscriber,
    Subscriber\ExecutionSucceededSubscriber
{
    private float $startTime;
    private string $currentQuery;

    public function __construct(
        private float $thresholdSeconds = 1.0
    ) {}

    public function executionStarts(Event\ExecutionStartsEvent $event): void {
        $this->startTime = microtime(true);
        $this->currentQuery = $event->query;
    }

    public function executionSucceeded(Event\ExecutionSucceededEvent $event): void {
        $duration = microtime(true) - $this->startTime;

        if ($duration > $this->thresholdSeconds) {
            error_log(sprintf(
                "Slow query detected (%.2fs): %s",
                $duration,
                $this->currentQuery
            ));
        }
    }
}

$pdo = new PDO('mysql:host=localhost;dbname=mydb', 'user', 'pass');
$pdo->addSubscriber(new SlowQueryDetector(thresholdSeconds: 0.5));
```

### Error Tracker

```php
use PeoplePath\PdoApm\PDO;
use PeoplePath\PdoApm\Event;
use PeoplePath\PdoApm\Subscriber;

class ErrorTracker implements Subscriber\ExecutionFailedSubscriber
{
    public function executionFailed(Event\ExecutionFailedEvent $event): void {
        error_log(sprintf(
            "Query failed: %s\nParameters: %s\nError: %s",
            $event->exception->getMessage(),
            json_encode($event->params),
            $event->exception->getTraceAsString()
        ));

        // Send to error tracking service
        // $this->sentryClient->captureException($event->exception);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->addSubscriber(new ErrorTracker());
```

### Transaction Monitor

```php
use PeoplePath\PdoApm\PDO;
use PeoplePath\PdoApm\Event;
use PeoplePath\PdoApm\Subscriber;

class TransactionMonitor implements
    Subscriber\TransactionBeginSubscriber,
    Subscriber\TransactionCommitSubscriber,
    Subscriber\TransactionRollbackSubscriber
{
    private int $activeTransactions = 0;

    public function transactionBegin(Event\TransactionBeginEvent $event): void {
        $this->activeTransactions++;
        echo "Transaction started (active: {$this->activeTransactions})\n";
    }

    public function transactionCommit(Event\TransactionCommitEvent $event): void {
        $this->activeTransactions--;
        echo "Transaction committed\n";
    }

    public function transactionRollback(Event\TransactionRollbackEvent $event): void {
        $this->activeTransactions--;
        echo "Transaction rolled back\n";
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->addSubscriber(new TransactionMonitor());

$pdo->beginTransaction();
// ... queries ...
$pdo->commit();
```

### Complete Example

See [examples/query-profiler.php](examples/query-profiler.php) for a fully-featured query profiling implementation with detailed reporting.

Run the example:

```bash
php examples/query-profiler.php
```

## API Reference

### `PeoplePath\PdoApm\PDO`

Extends `\PDO` with event notification capabilities.

#### Methods

- `addSubscriber(Subscriber $subscriber): void` - Register an event subscriber
- `notifySubscribers(Event $event): void` - Manually notify all subscribers of an event

All standard PDO methods work exactly as expected.

### Event Properties

#### `ExecutionStartsEvent`
- `string $query` - The SQL query being executed

#### `ExecutionSucceededEvent`
- `int $rowCount` - Number of rows affected
- `?array $params` - Parameters passed to the query (if any)

#### `ExecutionFailedEvent`
- `PDOException $exception` - The exception that was thrown
- `?array $params` - Parameters passed to the query (if any)

#### `PrepareEvent`
- `string $query` - The SQL query being prepared

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This library is licensed under the MIT License. See [LICENSE](LICENSE) for details.

## Authors

- **Ondřej Ešler** - [ondrej.esler@peoplepath.com](mailto:ondrej.esler@peoplepath.com)

## Use Cases

- **Performance Monitoring** - Track query execution times in production
- **Development Debugging** - Log all queries during development
- **Query Optimization** - Identify slow queries and N+1 problems
- **Error Tracking** - Capture and report database errors
- **Audit Logging** - Track all database operations for compliance
- **Testing** - Verify database interactions in unit tests
- **Metrics Collection** - Gather database statistics for dashboards

## Why PDO APM?

- **Non-invasive**: Drop-in replacement for PDO with zero configuration
- **Flexible**: Subscribe only to events you need
- **Performant**: Minimal overhead when no subscribers are attached
- **Type-safe**: Full PHP 8.4+ type hints for better IDE support
- **Well-tested**: Comprehensive test suite ensures reliability
- **Framework-agnostic**: Works with any PHP application or framework

---

Made with ❤️ by [PeoplePath](https://peoplepath.com)
