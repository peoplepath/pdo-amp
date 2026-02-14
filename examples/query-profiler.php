<?php

declare(strict_types=1);

/**
 * Database Query Profiler Example
 *
 * This example demonstrates how to use PDO APM to profile database queries
 * and track execution times, row counts, and other metrics.
 */

require __DIR__.'/../vendor/autoload.php';

use PeoplePath\PdoApm\Event;
use PeoplePath\PdoApm\PDO;
use PeoplePath\PdoApm\Subscriber;

/**
 * Query Profiler - tracks execution time and metrics for all queries
 */
class QueryProfiler implements Subscriber\ExecutionFailedSubscriber, Subscriber\ExecutionStartsSubscriber, Subscriber\ExecutionSucceededSubscriber, Subscriber\PrepareSubscriber, Subscriber\TransactionBeginSubscriber, Subscriber\TransactionCommitSubscriber, Subscriber\TransactionRollbackSubscriber
{
    private array $queries = [];

    private ?float $currentStartTime = null;

    private ?string $currentQuery = null;

    private int $transactionDepth = 0;

    public function executionStarts(Event\ExecutionStartsEvent $event): void
    {
        $this->currentQuery = $event->query;
        $this->currentStartTime = microtime(true);
    }

    public function executionSucceeded(Event\ExecutionSucceededEvent $event): void
    {
        if ($this->currentStartTime !== null) {
            $duration = microtime(true) - $this->currentStartTime;

            $this->queries[] = [
                'query' => $this->currentQuery,
                'duration' => $duration,
                'rows' => $event->rowCount,
                'params' => $event->params,
                'status' => 'success',
                'in_transaction' => $this->transactionDepth > 0,
            ];

            $this->currentStartTime = null;
            $this->currentQuery = null;
        }
    }

    public function executionFailed(Event\ExecutionFailedEvent $event): void
    {
        if ($this->currentStartTime !== null) {
            $duration = microtime(true) - $this->currentStartTime;

            $this->queries[] = [
                'query' => $this->currentQuery,
                'duration' => $duration,
                'rows' => 0,
                'params' => $event->params,
                'status' => 'failed',
                'error' => $event->exception->getMessage(),
                'in_transaction' => $this->transactionDepth > 0,
            ];

            $this->currentStartTime = null;
            $this->currentQuery = null;
        }
    }

    public function prepare(Event\PrepareEvent $event): void
    {
        // Track prepared statements
        echo '📝 Prepared: '.$this->truncateQuery($event->query)."\n";
    }

    public function transactionBegin(Event\TransactionBeginEvent $event): void
    {
        $this->transactionDepth++;
        echo "🔓 Transaction started (depth: {$this->transactionDepth})\n";
    }

    public function transactionCommit(Event\TransactionCommitEvent $event): void
    {
        echo "✅ Transaction committed (depth: {$this->transactionDepth})\n";
        $this->transactionDepth--;
    }

    public function transactionRollback(Event\TransactionRollbackEvent $event): void
    {
        echo "🔄 Transaction rolled back (depth: {$this->transactionDepth})\n";
        $this->transactionDepth--;
    }

    public function getQueries(): array
    {
        return $this->queries;
    }

    public function printReport(): void
    {
        echo "\n".str_repeat('=', 80)."\n";
        echo "📊 QUERY PROFILING REPORT\n";
        echo str_repeat('=', 80)."\n\n";

        $totalTime = 0;
        $totalQueries = count($this->queries);
        $successfulQueries = 0;
        $failedQueries = 0;

        foreach ($this->queries as $i => $query) {
            $num = $i + 1;
            $status = $query['status'] === 'success' ? '✅' : '❌';
            $txn = $query['in_transaction'] ? '[TXN] ' : '';

            echo "Query #{$num}: {$status} {$txn}\n";
            echo '  SQL: '.$this->truncateQuery($query['query'])."\n";
            echo '  Duration: '.$this->formatDuration($query['duration'])."\n";

            if ($query['status'] === 'success') {
                echo "  Rows affected: {$query['rows']}\n";
                if ($query['params']) {
                    echo '  Parameters: '.json_encode($query['params'])."\n";
                }
                $successfulQueries++;
                $totalTime += $query['duration'];
            } else {
                echo "  Error: {$query['error']}\n";
                if ($query['params']) {
                    echo '  Parameters: '.json_encode($query['params'])."\n";
                }
                $failedQueries++;
            }

            echo "\n";
        }

        echo str_repeat('-', 80)."\n";
        echo "📈 STATISTICS\n";
        echo str_repeat('-', 80)."\n";
        echo "Total queries: {$totalQueries}\n";
        echo "Successful: {$successfulQueries}\n";
        echo "Failed: {$failedQueries}\n";
        echo 'Total time: '.$this->formatDuration($totalTime)."\n";

        if ($successfulQueries > 0) {
            $avgTime = $totalTime / $successfulQueries;
            echo 'Average time: '.$this->formatDuration($avgTime)."\n";

            // Find slowest query
            $slowest = null;
            $slowestDuration = 0;
            foreach ($this->queries as $query) {
                if ($query['status'] === 'success' && $query['duration'] > $slowestDuration) {
                    $slowest = $query;
                    $slowestDuration = $query['duration'];
                }
            }

            if ($slowest) {
                echo "\n🐌 Slowest query (".$this->formatDuration($slowestDuration)."):\n";
                echo '   '.$this->truncateQuery($slowest['query'])."\n";
            }
        }

        echo str_repeat('=', 80)."\n";
    }

    private function truncateQuery(string $query, int $length = 100): string
    {
        $query = preg_replace('/\s+/', ' ', trim($query));

        return strlen($query) > $length ? substr($query, 0, $length).'...' : $query;
    }

    private function formatDuration(float $seconds): string
    {
        if ($seconds < 0.001) {
            return sprintf('%.2f μs', $seconds * 1_000_000);
        } elseif ($seconds < 1) {
            return sprintf('%.2f ms', $seconds * 1_000);
        } else {
            return sprintf('%.2f s', $seconds);
        }
    }
}

// =============================================================================
// Example Usage
// =============================================================================

echo "🚀 PDO APM Query Profiler Example\n";
echo str_repeat('=', 80)."\n\n";

// Create PDO instance with profiler
$pdo = new PDO('sqlite::memory:');
$profiler = new QueryProfiler;
$pdo->addSubscriber($profiler);

echo "🔧 Setting up database...\n\n";

// Create tables
$pdo->exec('
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
');

$pdo->exec('
    CREATE TABLE posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        content TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )
');

echo "✅ Tables created\n\n";

// Insert sample data using prepared statements
echo "📝 Inserting sample data...\n\n";

$stmt = $pdo->prepare('INSERT INTO users (name, email) VALUES (?, ?)');
$users = [
    ['Alice Johnson', 'alice@example.com'],
    ['Bob Smith', 'bob@example.com'],
    ['Carol Davis', 'carol@example.com'],
];

foreach ($users as $user) {
    $stmt->execute($user);
}

// Insert posts
$stmt = $pdo->prepare('INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)');
$posts = [
    [1, 'Getting Started with PDO', 'PDO is a great way to interact with databases...'],
    [1, 'Advanced SQL Techniques', 'Learn about joins, subqueries, and more...'],
    [2, 'My First Blog Post', 'Hello world! This is my first post...'],
    [3, 'Database Optimization Tips', 'Here are some tips to make your queries faster...'],
];

foreach ($posts as $post) {
    $stmt->execute($post);
}

echo "✅ Sample data inserted\n\n";

// Demonstrate various query types
echo "🔍 Running various queries...\n\n";

// Simple SELECT
$result = $pdo->query('SELECT * FROM users');
echo 'Found '.$result->rowCount()." users\n\n";

// Prepared statement with parameters
$stmt = $pdo->prepare('SELECT * FROM posts WHERE user_id = ?');
$stmt->execute([1]);
echo 'Found '.$stmt->rowCount()." posts by user 1\n\n";

// JOIN query
$result = $pdo->query('
    SELECT users.name, COUNT(posts.id) as post_count
    FROM users
    LEFT JOIN posts ON users.id = posts.user_id
    GROUP BY users.id
');
echo "User post counts calculated\n\n";

// Transaction example
echo "💾 Demonstrating transaction...\n";
$pdo->beginTransaction();

try {
    $pdo->exec('INSERT INTO users (name, email) VALUES ("Dave Wilson", "dave@example.com")');
    $pdo->exec('UPDATE posts SET title = "Updated Title" WHERE id = 1');
    $pdo->commit();
    echo "✅ Transaction completed\n\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo '❌ Transaction failed: '.$e->getMessage()."\n\n";
}

// Demonstrate failed query (intentional error for profiling)
echo "⚠️  Attempting invalid query (for demonstration)...\n";
try {
    $pdo->query('SELECT * FROM non_existent_table');
} catch (Exception $e) {
    echo "Caught expected error\n\n";
}

// Print profiling report
$profiler->printReport();

echo "\n✨ Example completed!\n";
