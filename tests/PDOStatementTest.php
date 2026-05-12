<?php

declare(strict_types=1);

namespace PeoplePath\PdoApm;

use PHPUnit\Framework\TestCase;

/**
 * Tests for PDOStatement delegation methods
 */
final class PDOStatementTest extends TestCase
{
    public function test_fetch_column_returns_single_column_value(): void
    {
        $pdo = $this->createPDO();
        $pdo->exec('CREATE TABLE test_table (id INTEGER, name TEXT, age INTEGER)');
        $pdo->exec("INSERT INTO test_table VALUES (1, 'Alice', 30), (2, 'Bob', 25)");

        $stmt = $pdo->prepare('SELECT * FROM test_table ORDER BY id');
        $this->assertNotFalse($stmt);
        $stmt->execute();

        // fetchColumn defaults to column 0 (id)
        $this->assertSame(1, $stmt->fetchColumn());
        $this->assertSame(2, $stmt->fetchColumn());
        $this->assertFalse($stmt->fetchColumn()); // No more rows
    }

    public function test_fetch_column_with_specific_column(): void
    {
        $pdo = $this->createPDO();
        $pdo->exec('CREATE TABLE test_table (id INTEGER, name TEXT, age INTEGER)');
        $pdo->exec("INSERT INTO test_table VALUES (1, 'Alice', 30)");

        $stmt = $pdo->prepare('SELECT * FROM test_table');
        $this->assertNotFalse($stmt);
        $stmt->execute();

        // Fetch the second column (name)
        $this->assertSame('Alice', $stmt->fetchColumn(1));
    }

    public function test_fetch_object_returns_stdclass_by_default(): void
    {
        $pdo = $this->createPDO();
        $pdo->exec('CREATE TABLE test_table (id INTEGER, name TEXT)');
        $pdo->exec("INSERT INTO test_table VALUES (1, 'Alice'), (2, 'Bob')");

        $stmt = $pdo->prepare('SELECT * FROM test_table ORDER BY id');
        $this->assertNotFalse($stmt);
        $stmt->execute();

        $obj1 = $stmt->fetchObject();
        $this->assertInstanceOf(\stdClass::class, $obj1);
        $this->assertSame(1, $obj1->id);
        $this->assertSame('Alice', $obj1->name);

        $obj2 = $stmt->fetchObject();
        $this->assertInstanceOf(\stdClass::class, $obj2);
        $this->assertSame(2, $obj2->id);
        $this->assertSame('Bob', $obj2->name);

        $this->assertFalse($stmt->fetchObject()); // No more rows
    }

    public function test_fetch_object_with_custom_class(): void
    {
        $pdo = $this->createPDO();
        $pdo->exec('CREATE TABLE test_table (id INTEGER, name TEXT, age INTEGER)');
        $pdo->exec("INSERT INTO test_table VALUES (1, 'Alice', 30)");

        $stmt = $pdo->prepare('SELECT * FROM test_table');
        $this->assertNotFalse($stmt);
        $stmt->execute();

        $obj = $stmt->fetchObject(TestUser::class);
        $this->assertInstanceOf(TestUser::class, $obj);
        $this->assertSame(1, $obj->id);
        $this->assertSame('Alice', $obj->name);
        $this->assertSame(30, $obj->age);
    }

    public function test_fetch_object_with_constructor_args(): void
    {
        $pdo = $this->createPDO();
        $pdo->exec('CREATE TABLE test_table (name TEXT)');
        $pdo->exec("INSERT INTO test_table VALUES ('Alice')");

        $stmt = $pdo->prepare('SELECT * FROM test_table');
        $this->assertNotFalse($stmt);
        $stmt->execute();

        $obj = $stmt->fetchObject(TestUserWithConstructor::class, ['default_age' => 25]);
        $this->assertInstanceOf(TestUserWithConstructor::class, $obj);
        $this->assertSame('Alice', $obj->name);
        $this->assertSame(25, $obj->constructorAge);
    }

    public function test_close_cursor_closes_statement(): void
    {
        $pdo = $this->createPDO();
        $pdo->exec('CREATE TABLE test_table (id INTEGER)');
        $pdo->exec("INSERT INTO test_table VALUES (1), (2), (3)");

        $stmt = $pdo->prepare('SELECT * FROM test_table');
        $this->assertNotFalse($stmt);
        $stmt->execute();

        // Fetch one row
        $this->assertNotFalse($stmt->fetch());

        // Close cursor
        $this->assertTrue($stmt->closeCursor());

        // After closing, we can execute again
        $this->assertTrue($stmt->execute());
        $this->assertNotFalse($stmt->fetch());
    }

    public function test_set_fetch_mode_changes_default_fetch_behavior(): void
    {
        $pdo = $this->createPDO();
        $pdo->exec('CREATE TABLE test_table (id INTEGER, name TEXT)');
        $pdo->exec("INSERT INTO test_table VALUES (1, 'Alice')");

        $stmt = $pdo->prepare('SELECT * FROM test_table');
        $this->assertNotFalse($stmt);
        $stmt->execute();

        // Default is FETCH_ASSOC (set in createPDO)
        $row1 = $stmt->fetch();
        $this->assertSame(['id' => 1, 'name' => 'Alice'], $row1);

        // Execute again and change fetch mode to FETCH_NUM
        $stmt->execute();
        $stmt->setFetchMode(\PDO::FETCH_NUM); // Always returns true
        $row2 = $stmt->fetch();
        $this->assertSame([1, 'Alice'], $row2);
    }

    public function test_set_fetch_mode_with_fetch_class(): void
    {
        $pdo = $this->createPDO();
        $pdo->exec('CREATE TABLE test_table (id INTEGER, name TEXT, age INTEGER)');
        $pdo->exec("INSERT INTO test_table VALUES (1, 'Alice', 30)");

        $stmt = $pdo->prepare('SELECT * FROM test_table');
        $this->assertNotFalse($stmt);
        $stmt->setFetchMode(\PDO::FETCH_CLASS, TestUser::class); // Always returns true
        $stmt->execute();

        $obj = $stmt->fetch();
        $this->assertInstanceOf(TestUser::class, $obj);
        $this->assertSame(1, $obj->id);
        $this->assertSame('Alice', $obj->name);
    }

    public function test_next_rowset_with_multiple_result_sets(): void
    {
        // SQLite doesn't support multiple result sets
        $pdo = $this->createPDO();
        $pdo->exec('CREATE TABLE test_table (id INTEGER)');
        $pdo->exec("INSERT INTO test_table VALUES (1)");

        $stmt = $pdo->prepare('SELECT * FROM test_table');
        $this->assertNotFalse($stmt);
        $stmt->execute();

        // For SQLite, this throws an exception as multiple rowsets are not supported
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('driver does not support multiple rowsets');
        $stmt->nextRowset();
    }

    public function test_set_attribute_on_statement(): void
    {
        $pdo = $this->createPDO();
        $stmt = $pdo->prepare('SELECT 1');
        $this->assertNotFalse($stmt);

        // SQLite doesn't support setting attributes on statements
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage("doesn't support setting attributes");
        $stmt->setAttribute(\PDO::ATTR_CURSOR, \PDO::CURSOR_SCROLL);
    }

    public function test_get_attribute_from_statement(): void
    {
        $pdo = $this->createPDO();
        $stmt = $pdo->prepare('SELECT 1');
        $this->assertNotFalse($stmt);

        // Try to get an attribute
        // Note: Not all drivers support all attributes, but the method should be callable
        try {
            $value = $stmt->getAttribute(\PDO::ATTR_CURSOR);
            // If successful, it should return a value
            $this->assertIsInt($value);
        } catch (\PDOException $e) {
            // Some drivers don't support getAttribute on statements
            $this->assertStringContainsString("doesn't support getting", $e->getMessage());
        }
    }

    public function test_error_code_returns_null_on_success(): void
    {
        $pdo = $this->createPDO();
        $stmt = $pdo->prepare('SELECT 1');
        $this->assertNotFalse($stmt);
        $stmt->execute();

        // On success, errorCode should return '00000' or null
        $errorCode = $stmt->errorCode();
        $this->assertTrue($errorCode === null || $errorCode === '00000');
    }

    public function test_error_code_returns_code_on_error(): void
    {
        $pdo = $this->createPDO(errmode: PDO::ERRMODE_SILENT);
        $stmt = $pdo->prepare('SELECT * FROM non_existent_table');

        if ($stmt !== false) {
            $stmt->execute();
            $errorCode = $stmt->errorCode();

            // Should have an error code (not null or '00000')
            $this->assertNotNull($errorCode);
            $this->assertNotSame('00000', $errorCode);
        } else {
            // If prepare fails, check error on PDO
            $this->assertNotSame('00000', $pdo->errorCode());
        }
    }

    public function test_error_info_returns_array_on_success(): void
    {
        $pdo = $this->createPDO();
        $stmt = $pdo->prepare('SELECT 1');
        $this->assertNotFalse($stmt);
        $stmt->execute();

        $errorInfo = $stmt->errorInfo();
        $this->assertCount(3, $errorInfo);

        // First element is SQLSTATE code, should be '00000' on success
        $this->assertTrue($errorInfo[0] === '00000' || $errorInfo[0] === null);
    }

    public function test_error_info_returns_details_on_error(): void
    {
        $pdo = $this->createPDO(errmode: PDO::ERRMODE_SILENT);
        $pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT NOT NULL UNIQUE)');
        $pdo->exec("INSERT INTO t (id, name) VALUES (1, 'foo')");

        $stmt = $pdo->prepare('INSERT INTO t (id, name) VALUES (1, ?)');
        $this->assertNotFalse($stmt);

        $ok = $stmt->execute(['bar']);   // false — duplicate PK

        $this->assertFalse($ok);

        // errorInfo() should return error details on failure
        $errorInfo = $stmt->errorInfo();
        $this->assertCount(3, $errorInfo);

        // First element is SQLSTATE code (should not be success code)
        $this->assertNotNull($errorInfo[0]);
        $this->assertNotSame('00000', $errorInfo[0]);

        // Third element should contain error message
        $this->assertIsString($errorInfo[2]);
        $this->assertStringContainsString('constraint failed', strtolower($errorInfo[2]));
    }

    public function test_debug_dump_params_is_callable(): void
    {
        $pdo = $this->createPDO();
        $stmt = $pdo->prepare('SELECT :name, :age');
        $this->assertNotFalse($stmt);
        $stmt->bindValue(':name', 'Alice');
        $stmt->bindValue(':age', 30);

        // Capture output from debugDumpParams
        ob_start();
        $stmt->debugDumpParams();
        $output = ob_get_clean();

        // Should have produced some output
        $this->assertIsString($output);
        $this->assertNotEmpty($output);

        // Output should contain SQL query info
        $this->assertStringContainsString('SELECT', $output);
    }

    public function test_bind_column_binds_result_to_variable(): void
    {
        $pdo = $this->createPDO();
        $pdo->exec('CREATE TABLE test_table (id INTEGER, name TEXT)');
        $pdo->exec("INSERT INTO test_table VALUES (1, 'Alice'), (2, 'Bob')");

        $stmt = $pdo->prepare('SELECT * FROM test_table ORDER BY id');
        $this->assertNotFalse($stmt);
        $stmt->execute();

        // Bind columns to variables
        $id = null;
        $name = null;
        $stmt->bindColumn(1, $id); // Always returns true
        $stmt->bindColumn(2, $name); // Always returns true

        // Fetch rows - variables should be populated
        // Note: SQLite returns values as strings by default
        $stmt->fetch(\PDO::FETCH_BOUND);
        $this->assertSame('1', $id);
        $this->assertSame('Alice', $name);

        $stmt->fetch(\PDO::FETCH_BOUND);
        $this->assertSame('2', $id);
        $this->assertSame('Bob', $name);
    }

    public function test_bind_column_with_column_name(): void
    {
        $pdo = $this->createPDO();
        $pdo->exec('CREATE TABLE test_table (id INTEGER, name TEXT)');
        $pdo->exec("INSERT INTO test_table VALUES (1, 'Alice')");

        $stmt = $pdo->prepare('SELECT * FROM test_table');
        $this->assertNotFalse($stmt);
        $stmt->execute();

        // Bind using column name
        $name = null;
        $stmt->bindColumn('name', $name); // Always returns true

        $stmt->fetch(\PDO::FETCH_BOUND);
        $this->assertSame('Alice', $name);
    }

    public function test_bind_column_with_type_parameter(): void
    {
        $pdo = $this->createPDO();
        $pdo->exec('CREATE TABLE test_table (id INTEGER, is_active INTEGER)');
        $pdo->exec("INSERT INTO test_table VALUES (1, 1)");

        $stmt = $pdo->prepare('SELECT * FROM test_table');
        $this->assertNotFalse($stmt);
        $stmt->execute();

        // Bind with specific type
        $isActive = null;
        $stmt->bindColumn('is_active', $isActive, \PDO::PARAM_INT); // Always returns true

        $stmt->fetch(\PDO::FETCH_BOUND);
        $this->assertSame(1, $isActive);
    }

    public function test_row_count_returns_affected_rows(): void
    {
        $pdo = $this->createPDO();
        $pdo->exec('CREATE TABLE test_table (id INTEGER, name TEXT)');
        $pdo->exec("INSERT INTO test_table VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie')");

        $stmt = $pdo->prepare('UPDATE test_table SET name = ? WHERE id > ?');
        $this->assertNotFalse($stmt);
        $stmt->execute(['Updated', 1]);

        // Should have updated 2 rows (id 2 and 3)
        $this->assertSame(2, $stmt->rowCount());
    }

    public function test_column_count_returns_number_of_columns(): void
    {
        $pdo = $this->createPDO();
        $pdo->exec('CREATE TABLE test_table (id INTEGER, name TEXT, age INTEGER)');

        $stmt = $pdo->prepare('SELECT * FROM test_table');
        $this->assertNotFalse($stmt);
        $stmt->execute();

        // Should have 3 columns (id, name, age)
        $this->assertSame(3, $stmt->columnCount());
    }

    public function test_column_count_with_specific_columns(): void
    {
        $pdo = $this->createPDO();
        $pdo->exec('CREATE TABLE test_table (id INTEGER, name TEXT, age INTEGER)');

        $stmt = $pdo->prepare('SELECT id, name FROM test_table');
        $this->assertNotFalse($stmt);
        $stmt->execute();

        // Should have 2 columns (id, name)
        $this->assertSame(2, $stmt->columnCount());
    }

    public function test_fetch_with_different_modes(): void
    {
        $pdo = $this->createPDO();
        $pdo->exec('CREATE TABLE test_table (id INTEGER, name TEXT)');
        $pdo->exec("INSERT INTO test_table VALUES (1, 'Alice')");

        // Test FETCH_NUM
        $stmt = $pdo->prepare('SELECT * FROM test_table');
        $this->assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_NUM);
        $this->assertSame([1, 'Alice'], $row);

        // Test FETCH_BOTH
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_BOTH);
        // Check that all keys and values exist (order may vary)
        $this->assertIsArray($row);
        $this->assertCount(4, $row);
        $this->assertArrayHasKey(0, $row);
        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey(1, $row);
        $this->assertArrayHasKey('name', $row);
        $this->assertSame(1, $row[0]);
        $this->assertSame(1, $row['id']);
        $this->assertSame('Alice', $row[1]);
        $this->assertSame('Alice', $row['name']);
    }

    public function test_fetch_all_returns_all_rows(): void
    {
        $pdo = $this->createPDO();
        $pdo->exec('CREATE TABLE test_table (id INTEGER, name TEXT)');
        $pdo->exec("INSERT INTO test_table VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie')");

        $stmt = $pdo->prepare('SELECT * FROM test_table ORDER BY id');
        $this->assertNotFalse($stmt);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        $this->assertCount(3, $rows);
        $this->assertSame(['id' => 1, 'name' => 'Alice'], $rows[0]);
        $this->assertSame(['id' => 2, 'name' => 'Bob'], $rows[1]);
        $this->assertSame(['id' => 3, 'name' => 'Charlie'], $rows[2]);
    }

    public function test_fetch_all_with_fetch_column(): void
    {
        $pdo = $this->createPDO();
        $pdo->exec('CREATE TABLE test_table (id INTEGER, name TEXT)');
        $pdo->exec("INSERT INTO test_table VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie')");

        $stmt = $pdo->prepare('SELECT name FROM test_table ORDER BY id');
        $this->assertNotFalse($stmt);
        $stmt->execute();

        $names = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(['Alice', 'Bob', 'Charlie'], $names);
    }

    public function test_query_string_property_accessible(): void
    {
        $pdo = $this->createPDO();
        $query = 'SELECT :name, :age';
        $stmt = $pdo->prepare($query);
        $this->assertNotFalse($stmt);

        // Test that queryString property is accessible
        $this->assertSame($query, $stmt->queryString);
    }

    /**
     * @phpstan-param PDO::ERRMODE_* $errmode
     */
    private function createPDO(int $errmode = PDO::ERRMODE_EXCEPTION): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, $errmode);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }
}

/**
 * Test class for fetchObject testing
 */
class TestUser
{
    public int $id;
    public string $name;
    public int $age;
}

/**
 * Test class with constructor for fetchObject testing
 */
class TestUserWithConstructor
{
    public string $name;
    public int $constructorAge;

    public function __construct(int $default_age)
    {
        $this->constructorAge = $default_age;
    }
}
