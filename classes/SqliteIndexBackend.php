<?php declare(strict_types=1);

namespace Grav\Plugin\FlexObjects;

use Grav\Framework\Flex\Interfaces\FlexStorageInterface;
use PDO;
use PDOException;

/**
 * Optional SQLite-backed index for Flex Objects.
 *
 * Provides atomic upserts, WAL-mode concurrency, and indexed SQL queries
 * as an alternative to the default YAML flat-file index. Falls back
 * gracefully when pdo_sqlite is not available.
 *
 * The schema is generic (flex_index + flex_meta tables) but individual
 * Flex types define which columns exist via a column definition array
 * passed to the constructor.
 */
class SqliteIndexBackend
{
    /** @var string */
    private $dbPath;

    /** @var string */
    private $schemaVersion;

    /** @var PDO|null */
    private $pdo;

    /**
     * @var array Column definitions for the flex_index table.
     *
     * Each entry is ['name' => string, 'type' => string, 'default' => string|null].
     * The core columns (storage_key, storage_timestamp, checksum, key) are always
     * present. Additional columns are type-specific and supplied by the caller.
     */
    private $columns;

    /** @var array Index definitions as ['name' => 'columns_sql'] */
    private $indexes;

    /**
     * @param string $dbPath        Absolute path to the SQLite database file
     * @param string $schemaVersion Version string stored in flex_meta for rebuild detection
     * @param array  $columns       Extra column definitions beyond the core four.
     *                              Each entry: ['name' => string, 'type' => string, 'default' => string|null]
     * @param array  $indexes       Index definitions as ['index_name' => 'col1, col2']
     */
    public function __construct(string $dbPath, string $schemaVersion, array $columns = [], array $indexes = [])
    {
        $this->dbPath = $dbPath;
        $this->schemaVersion = $schemaVersion;
        $this->columns = $columns;
        $this->indexes = $indexes;
    }

    public static function isAvailable(): bool
    {
        return extension_loaded('pdo_sqlite');
    }

    public function needsRebuild(string $expectedVersion): bool
    {
        try {
            $pdo = $this->connect();
            $version = $this->getSchemaVersion($pdo);

            return $version !== $expectedVersion;
        } catch (PDOException $e) {
            return true;
        }
    }

    /**
     * @return array<string, array> Returns [storage_key => meta_array, ...]
     */
    public function getAllEntries(): array
    {
        try {
            $pdo = $this->connect();
            $stmt = $pdo->query('SELECT * FROM flex_index ORDER BY storage_key');
            $entries = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $key = $row['storage_key'];
                $entries[$key] = $this->rowToMeta($row);
            }

            return $entries;
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * @param string $where SQL WHERE clause (use ? placeholders)
     * @param array $params Bound parameter values
     * @return string[] Matching storage_keys
     */
    public function queryKeys(string $where, array $params = []): array
    {
        try {
            $pdo = $this->connect();
            $stmt = $pdo->prepare("SELECT storage_key FROM flex_index WHERE {$where}");
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function upsertEntry(string $key, array $meta): void
    {
        try {
            $pdo = $this->connect();
            $this->doUpsert($pdo, $key, $meta);
        } catch (PDOException $e) {
            // Silently fail — caller should fall back to YAML
        }
    }

    /**
     * @param array<string, array> $entries [storage_key => meta_array, ...]
     */
    public function upsertEntries(array $entries): void
    {
        try {
            $pdo = $this->connect();
            $pdo->beginTransaction();

            foreach ($entries as $key => $meta) {
                $this->doUpsert($pdo, (string)$key, $meta);
            }

            $pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }
    }

    public function removeEntry(string $key): void
    {
        try {
            $pdo = $this->connect();
            $stmt = $pdo->prepare('DELETE FROM flex_index WHERE storage_key = ?');
            $stmt->execute([$key]);
        } catch (PDOException $e) {
            // Silently fail
        }
    }

    /**
     * @param string[] $keys
     */
    public function removeEntries(array $keys): void
    {
        if (!$keys) {
            return;
        }

        try {
            $pdo = $this->connect();
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $stmt = $pdo->prepare("DELETE FROM flex_index WHERE storage_key IN ({$placeholders})");
            $stmt->execute(array_values($keys));
        } catch (PDOException $e) {
            // Silently fail
        }
    }

    /**
     * Full rebuild: drops all rows, re-scans storage, re-inserts everything.
     *
     * @param FlexStorageInterface $storage
     * @param callable $metaBuilder Callable that receives (&$meta, $data, $storage)
     */
    public function rebuild(FlexStorageInterface $storage, callable $metaBuilder): void
    {
        try {
            $pdo = $this->connect();

            // Get all existing keys from storage
            $existingKeys = $storage->getExistingKeys();
            if (!$existingKeys) {
                $pdo->exec('DELETE FROM flex_index');
                $this->setSchemaVersion($pdo, $this->schemaVersion);
                return;
            }

            // Read all data rows in chunks
            $allEntries = [];
            $chunks = array_chunk($existingKeys, 100, true);

            foreach ($chunks as $chunk) {
                $keys = array_fill_keys(array_keys($chunk), null);
                $rows = $storage->readRows($keys);

                $keyField = $storage->getKeyField();

                foreach ($rows as $storageKey => $row) {
                    if ($row === null) {
                        continue;
                    }

                    $entry = $existingKeys[$storageKey] + ['key' => $storageKey];
                    if ($keyField !== 'storage_key' && isset($row[$keyField])) {
                        $entry['key'] = $row[$keyField];
                    }

                    $metaBuilder($entry, $row, $storage);
                    $allEntries[$storageKey] = $entry;
                }
            }

            // Replace all data in a single transaction
            $pdo->beginTransaction();
            $pdo->exec('DELETE FROM flex_index');

            foreach ($allEntries as $key => $meta) {
                $this->doUpsert($pdo, (string)$key, $meta);
            }

            $this->setSchemaVersion($pdo, $this->schemaVersion);
            $pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }
    }

    /**
     * @return int Number of entries in the index
     */
    public function count(): int
    {
        try {
            $pdo = $this->connect();
            $result = $pdo->query('SELECT COUNT(*) FROM flex_index');

            return (int)$result->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    // -- Internal --------------------------------------------------------

    private function connect(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $dir = dirname($this->dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $pdo = new PDO('sqlite:' . $this->dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA synchronous=NORMAL');

        $this->ensureSchema($pdo);
        $this->pdo = $pdo;

        return $pdo;
    }

    private function ensureSchema(PDO $pdo): void
    {
        // Check if tables exist
        $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='flex_index'");
        if ($result->fetchColumn() !== false) {
            return;
        }

        // Build column definitions: core + type-specific
        $colDefs = [
            'storage_key       TEXT PRIMARY KEY',
            'storage_timestamp INTEGER NOT NULL',
            'checksum          TEXT',
            'key               TEXT NOT NULL',
        ];

        foreach ($this->columns as $col) {
            $line = $col['name'] . ' ' . $col['type'];
            if (isset($col['default'])) {
                $line .= ' DEFAULT ' . $col['default'];
            }
            $colDefs[] = $line;
        }

        $pdo->exec('CREATE TABLE flex_index (' . implode(', ', $colDefs) . ')');

        // Create indexes
        foreach ($this->indexes as $name => $columnsSql) {
            $pdo->exec("CREATE INDEX {$name} ON flex_index({$columnsSql})");
        }

        $pdo->exec(<<<'SQL'
CREATE TABLE flex_meta (
    meta_key   TEXT PRIMARY KEY,
    meta_value TEXT
)
SQL
        );
    }

    private function getSchemaVersion(PDO $pdo): ?string
    {
        try {
            $stmt = $pdo->prepare('SELECT meta_value FROM flex_meta WHERE meta_key = ?');
            $stmt->execute(['schema_version']);
            $value = $stmt->fetchColumn();

            return $value !== false ? (string)$value : null;
        } catch (PDOException $e) {
            return null;
        }
    }

    private function setSchemaVersion(PDO $pdo, string $version): void
    {
        $stmt = $pdo->prepare('INSERT OR REPLACE INTO flex_meta (meta_key, meta_value) VALUES (?, ?)');
        $stmt->execute(['schema_version', $version]);
    }

    private function doUpsert(PDO $pdo, string $key, array $meta): void
    {
        // Build column list: core + type-specific
        $allColumns = ['storage_key', 'storage_timestamp', 'checksum', 'key'];
        foreach ($this->columns as $col) {
            $allColumns[] = $col['name'];
        }

        $placeholders = implode(', ', array_fill(0, count($allColumns), '?'));
        $columnList = implode(', ', $allColumns);

        $stmt = $pdo->prepare("INSERT OR REPLACE INTO flex_index ({$columnList}) VALUES ({$placeholders})");

        // Build values: core first, then type-specific in order
        $values = [
            $key,
            (int)($meta['storage_timestamp'] ?? 0),
            $meta['checksum'] ?? null,
            (string)($meta['key'] ?? $key),
        ];

        foreach ($this->columns as $col) {
            $name = $col['name'];
            $type = strtoupper($col['type']);
            $raw = $meta[$name] ?? null;

            if ($raw === null) {
                $values[] = null;
            } elseif (strpos($type, 'INTEGER') !== false) {
                $values[] = (int)$raw;
            } else {
                $values[] = (string)$raw;
            }
        }

        $stmt->execute($values);
    }

    /**
     * Convert a SQLite row back to the meta array format expected by FlexIndex.
     */
    private function rowToMeta(array $row): array
    {
        $meta = [
            'storage_key' => $row['storage_key'],
            'storage_timestamp' => (int)$row['storage_timestamp'],
            'checksum' => $row['checksum'],
            'key' => $row['key'],
        ];

        foreach ($this->columns as $col) {
            $name = $col['name'];
            $type = strtoupper($col['type']);
            $raw = $row[$name] ?? null;

            if ($raw === null) {
                $meta[$name] = null;
            } elseif (strpos($type, 'INTEGER') !== false) {
                // Check if this is a boolean-like column (name contains 'is_')
                if (strpos($name, 'is_') === 0) {
                    $meta[$name] = (bool)$raw;
                } else {
                    $meta[$name] = (int)$raw;
                }
            } else {
                $meta[$name] = (string)$raw;
            }
        }

        return $meta;
    }
}
