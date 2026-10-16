<?php

declare(strict_types=1);

namespace Ttpryg\CartEngine\Database;

use PDO;

class DatabaseMigrator
{
    public function __construct(private readonly PDO $pdo, private readonly ?string $schemaPath = null) {}

    public function migrate(): void
    {
        $path = $this->schemaPath ?? __DIR__.'/../../database/schema.sql';
        if (! is_file($path) || ! is_readable($path)) {
            throw new \RuntimeException("Unable to load cart-engine schema file: {$path}");
        }

        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new \RuntimeException("Unable to load cart-engine schema file: {$path}");
        }

        $this->pdo->exec($sql);
    }
}
