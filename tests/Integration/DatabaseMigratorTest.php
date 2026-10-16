<?php

declare(strict_types=1);

namespace Ttpryg\CartEngine\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Ttpryg\CartEngine\Database\DatabaseMigrator;

class DatabaseMigratorTest extends TestCase
{
    private PDO $pdo;

    private string $schemaFile;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->schemaFile = sys_get_temp_dir().'/cart_engine_schema_'.uniqid().'.sql';
    }

    protected function tearDown(): void
    {
        if (is_file($this->schemaFile)) {
            unlink($this->schemaFile);
        }
    }

    private function tableNames(): array
    {
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return is_array($tables) ? $tables : [];
    }

    // POSITIVE CASE: Custom schema file creates all cart tables
    public function test_migrate_creates_tables_from_custom_schema(): void
    {
        file_put_contents($this->schemaFile, "
            CREATE TABLE IF NOT EXISTS carts (
                id VARCHAR(64) PRIMARY KEY,
                status VARCHAR(20) DEFAULT 'active'
            );
            CREATE TABLE IF NOT EXISTS cart_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cart_id VARCHAR(64) NOT NULL
            );
            CREATE TABLE IF NOT EXISTS cart_conditions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cart_id VARCHAR(64) NOT NULL,
                item_id VARCHAR(100) NULL
            );
        ");

        (new DatabaseMigrator($this->pdo, $this->schemaFile))->migrate();

        $tables = $this->tableNames();
        $this->assertContains('carts', $tables);
        $this->assertContains('cart_items', $tables);
        $this->assertContains('cart_conditions', $tables);
    }

    // POSITIVE CASE: Migration is idempotent (IF NOT EXISTS)
    public function test_migrate_is_idempotent(): void
    {
        file_put_contents($this->schemaFile, 'CREATE TABLE IF NOT EXISTS carts (id VARCHAR(64) PRIMARY KEY);');

        $migrator = new DatabaseMigrator($this->pdo, $this->schemaFile);
        $migrator->migrate();
        $migrator->migrate();

        $this->assertContains('carts', $this->tableNames());
    }

    // NEGATIVE CASE: Missing schema file throws RuntimeException with path
    public function test_migrate_throws_when_schema_file_is_missing(): void
    {
        $migrator = new DatabaseMigrator($this->pdo, '/nonexistent/path/schema.sql');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('/nonexistent/path/schema.sql');

        $migrator->migrate();
    }

    // POSITIVE CASE: Default bundled schema file resolves and is readable
    public function test_default_schema_file_exists_and_contains_expected_tables(): void
    {
        $path = dirname(__DIR__, 2).'/database/schema.sql';

        $this->assertFileExists($path);

        $sql = file_get_contents($path);
        $this->assertIsString($sql);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS carts', $sql);
        $this->assertStringContainsString('item_id VARCHAR(100)', $sql);
    }
}
