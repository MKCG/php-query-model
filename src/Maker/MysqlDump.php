<?php

namespace MKCG\Model\Maker;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class MysqlDump
{
    private $connection;
    private $databases = [];
    private $rules = [];
    private $schemaPath = '';
    private $modelNamespace = '';
    private $mapTableToEntity = [];
    private $filterable = [];

    private function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public static function makeModels(
        Connection $connection,
        string $schemaPath,
        string $modelNamespace,
        array $databases,
        array $rules = [],
        array $mapTableToEntity = []
    ) {
        $dumper = new static($connection);
        $dumper->schemaPath = $schemaPath;
        $dumper->modelNamespace = $modelNamespace;
        $dumper->databases = $databases;
        $dumper->rules = $rules;
        $dumper->mapTableToEntity = $mapTableToEntity;

        $dumper->listFilterableColumns();
        $schema = array_fill_keys($dumper->databases, []);

        foreach ($dumper->listTables($dumper->databases) as $table) {
            $schema[$table['table_schema']][$table['table_name']] = [];
        }

        foreach ($schema as $database => $dbConfig) {
            foreach ($dumper->listColumns($database, array_keys($dbConfig)) as $column) {
                $type = $column['data_type'];

                switch ($type) {
                    case 'bit':
                        $type = 'bool';
                        break;

                    case 'bigint':
                    case 'int':
                    case 'mediumint':
                    case 'smallint':
                    case 'tinyint';
                        $type = 'int';
                        break;

                    case 'decimal':
                    case 'float':
                    case 'double':
                        $type = 'float';
                        break;

                    case 'date':
                    case 'datetime':
                    case 'timestamp':
                        $type = 'datetime';
                        break;

                    case 'char':
                    case 'tinytext':
                    case 'mediumtext':
                    case 'longtext':
                    case 'text':
                    case 'varchar':
                        $type = 'string';
                        break;

                    case 'blob':
                    case 'longblob':
                    case 'tinyblob':
                        $type = 'binary';
                        break;

                    case 'enum':
                        $type = '';
                        break;

                    default:
                        // var_dump($type);
                        break;
                }

                $schema[$database][$column['table_name']][$column['column_name']] = [
                    'type' => $type,
                    'isPrimary' => $column['column_key'] === 'PRI'
                ];
            }
        }

        $foreignKeys = $dumper->listForeignKeys();

        foreach ($foreignKeys as $fkDatabase => $fkTableConfig) {
            if (!isset($schema[$fkDatabase])) {
                continue;
            }

            foreach ($fkTableConfig as $fkTable => $fkFieldConfig) {
                if (!isset($schema[$fkDatabase][$fkTable])) {
                    continue;
                }

                foreach ($fkFieldConfig as $fkField => $fkConfig) {
                    if (!isset($schema[$fkDatabase][$fkTable][$fkField])) {
                        continue;
                    }

                    $schema[$fkDatabase][$fkTable][$fkField]['relations'] = $fkConfig['relations'];
                }
            }
        }

        $ids = [];

        foreach ($schema as $database => $dbConfig) {
            foreach ($dbConfig as $table => $fields) {
                foreach ($fields as $field => $config) {
                    if ($field === 'id'
                        || strpos($field, 'id_') === 0
                        || strpos($field, '_id') === strlen($field) - 3
                    ) {
                        $ids[] = [ $database, $table, $field ];
                    }
                }
            }
        }

        foreach ($dumper->rules as $search => $field) {
            $matches = array_filter($ids, function ($id) use ($search, $field) {
                return (!empty($field[3]) && $id[2] === $search)    // Exact matching
                    || strpos($id[2], $search) !== false;           // Partial matching
            });

            $matches = array_values($matches);

            if (empty($matches)) {
                continue;
            }

            if (!isset($schema[$field[0]][$field[1]][$field[2]]['relations'])) {
                $schema[$field[0]][$field[1]][$field[2]]['relations'] = [];
            }

            $schema[$field[0]][$field[1]][$field[2]]['relations'] = array_merge(
                $schema[$field[0]][$field[1]][$field[2]]['relations'],
                $matches
            );

            foreach ($matches as $match) {
                if (!isset($schema[$match[0]][$match[1]][$match[2]]['relations'])) {
                    $schema[$match[0]][$match[1]][$match[2]]['relations'] = [];
                }

                $schema[$match[0]][$match[1]][$match[2]]['relations'][] = $field;
            }
        }

        foreach ($schema as $database => $config) {
            $dumper->updateSchema($database, $config);
        }
    }

    private function updateSchema(string $database, array $tables)
    {
        $namespace = $this->snakeToPascalCase($database);
        $namespace = $this->avoidPhpCollision($namespace);

        foreach ($tables as $table => $fields) {
            $classname = $this->snakeToPascalCase($table);
            $this->updateTableSchema($namespace, $classname, $database, $table, $fields);
        }
    }

    private function updateTableSchema(string $namespace, string $classname, string $database, string $table, array $fields)
    {
        if (!is_dir($this->schemaPath)) {
            mkdir($this->schemaPath);
        }

        $knownSchemas = scandir($this->schemaPath);

        if (!in_array($namespace, $knownSchemas)) {
            mkdir($this->schemaPath . '/' . $namespace);
        }

        $namespacePath = $this->schemaPath . '/' . $namespace;
        $knownClasses = scandir($namespacePath);

        $classFile = $classname . '.php';
        $classFilePath = $namespacePath . DIRECTORY_SEPARATOR . $classname . '.php';

        $classContent = $this->makeClass($namespace, $classname, $database, $table, $fields);

        // if (!in_array($classFile, $knownClasses)) {
            file_put_contents($classFilePath, $classContent);
        // }
    }

    private function makeClass($namespace, $classname, $database, $table, array $fields)
    {
        $fieldsName = array_keys($fields);
        $fieldsName = array_map(function($field) {
            return "            '" . $field . "',";
        }, $fieldsName);
        $fieldsName = implode("\n", $fieldsName);

        $primaryKeys = [];
        $relations = [];
        $filterable = [];

        foreach ($fields as $field => $config) {
            if (!empty($config['isPrimary'])) {
                $primaryKeys[] = $field;
                $filterable[] = $field;
            }

            if (isset($this->filterable[$database][$table])
                && in_array($field, $this->filterable[$database][$table])
            ) {
                $filterable[] = $field;
            }

            if (empty($config['relations'])) {
                continue;
            }

            $filterable[] = $field;

            foreach ($config['relations'] as $relation) {
                if ($relation[0] === $database
                    && $relation[1] === $table
                    && $relation[2] === $field
                ) {
                    continue;
                }

                $relationClassName = '\\'
                    . $this->modelNamespace . '\\'
                    . $this->avoidPhpCollision($this->snakeToPascalCase($relation[0]))
                    . '\\' . $this->avoidPhpCollision($this->snakeToPascalCase($relation[1]))
                ;

                $relationHash = implode('|', [
                    $relation[0],
                    $relation[1],
                    $relation[2]
                ]);

                $relations[$relationHash] = <<<FOREIGN

        \$this->addRelation('${relation[0]}_${relation[1]}_${relation[2]}', ${relationClassName}::class, '${field}', '${relation[2]}', true);
FOREIGN;
            }
        }

        ksort($relations);
        $relations = implode("\n", $relations);

        $entityClass = isset($this->mapTableToEntity[$database . '.' . $table])
            ? $this->mapTableToEntity[$database . '.' . $table] . '::class'
            : null;

        sort($primaryKeys);
        $primaryKeys = $primaryKeys !== []
            ? "'" . implode("', '", $primaryKeys) . "'"
            : [];

        $filterable = array_unique($filterable);
        sort($filterable);
        $filterable = $filterable !== []
            ? "'" . implode("', '", $filterable) . "'"
            : [];

        $fileContent = <<<CLASS
<?php

namespace $this->modelNamespace\\${namespace};

use MKCG\Model\GenericSchema;

class ${classname} extends GenericSchema
{
    protected \$name = '${database}.${table}';
CLASS;

        if ($entityClass !== null) {
            $fileContent .= <<<CLASS

    protected \$entityClass = ${entityClass};
CLASS;
        }

        if ($primaryKeys !== []) {
            $fileContent .= <<<CLASS

    protected \$primaryKeys = [${primaryKeys}];
CLASS;
        }

        if ($filterable !== []) {
            $fileContent .= <<<CLASS

    protected \$filterableFields = [${filterable}];
CLASS;
        }

        $fileContent .= <<<CLASS


    protected \$types = [
        'default' => [
${fieldsName}
        ]
    ];
CLASS;

        if ($relations !== '') {
            $fileContent .= <<<CLASS

    public function initRelations() : self
${relations}
        return \$this;
    }
CLASS;
        }

        $fileContent .= <<<CLASS

}

CLASS;
        return $fileContent;    
    }

    private function snakeToPascalCase(string $text)
    {
        $parts = explode('_', $text);
        $parts = array_map('ucfirst', $parts);
        return implode('', $parts);
    }

    private function listTables(array $databases) : array
    {
        $queryBuilder = $this->connection
            ->createQueryBuilder()
            ->select(['table_schema', 'table_name'])
            ->from('information_schema.tables');

        $queryBuilder
            ->andWhere($queryBuilder->expr()->in('table_schema', ':table_schema'))
            ->setParameter('table_schema', $databases, Connection::PARAM_STR_ARRAY);

        return $queryBuilder->execute()->fetchAll();
    }

    private function listColumns(string $database, array $tables) : array
    {
        $queryBuilder = $this->connection
            ->createQueryBuilder()
            ->select(['table_name', 'column_name', 'data_type', 'column_type', 'column_key'])
            ->from('information_schema.columns');

        $queryBuilder
            ->where('table_schema = :database')
            ->andWhere($queryBuilder->expr()->in('table_name', ':tables'))
            ->setParameter('database', $database, ParameterType::STRING)
            ->setParameter('tables', $tables, Connection::PARAM_STR_ARRAY);

        return $queryBuilder->execute()->fetchAll();
    }

    private function listForeignKeys() : array
    {
        $foreignKeys = $this->connection
            ->createQueryBuilder()
            ->select(['id', 'for_name', 'ref_name'])
            ->from('information_schema.innodb_sys_foreign')
            ->execute()
            ->fetchAll();

        $foreignKeysCols = $this->connection
            ->createQueryBuilder()
            ->select(['id', 'for_col_name', 'ref_col_name'])
            ->from('information_schema.innodb_sys_foreign_cols')
            ->execute()
            ->fetchAll();

        $foreignKeys = array_column($foreignKeys, null, 'id');
        $foreignKeysCols = array_column($foreignKeysCols, null, 'id');

        $config = [];

        foreach ($foreignKeys as $id => $foreignKey) {
            list($forDatabase, $forTable) = explode('/', $foreignKey['for_name']);
            list($refDatabase, $refTable) = explode('/', $foreignKey['ref_name']);

            if (!isset($config[$forDatabase])) {
                $config[$forDatabase] = [];
            }

            if (!isset($config[$forDatabase][$forTable])) {
                $config[$forDatabase][$forTable] = [];
            }

            if (!isset($config[$forDatabase][$forTable][$foreignKeysCols[$id]['for_col_name']])) {
                $config[$forDatabase][$forTable][$foreignKeysCols[$id]['for_col_name']] = [
                    'relations' => []
                ];
            }

            $config[$forDatabase][$forTable][$foreignKeysCols[$id]['for_col_name']]['relations'][] = [
                $refDatabase,
                $refTable,
                $foreignKeysCols[$id]['ref_col_name']
            ];

            if (!isset($config[$refDatabase])) {
                $config[$refDatabase] = [];
            }

            if (!isset($config[$refDatabase][$refTable])) {
                $config[$refDatabase][$refTable] = [];
            }

            if (!isset($config[$refDatabase][$refTable][$foreignKeysCols[$id]['ref_col_name']])) {
                $config[$refDatabase][$refTable][$foreignKeysCols[$id]['ref_col_name']] = [
                    'relations' => []
                ];
            }

            $config[$refDatabase][$refTable][$foreignKeysCols[$id]['ref_col_name']]['relations'][] = [
                $forDatabase,
                $forTable,
                $foreignKeysCols[$id]['for_col_name']
            ];
        }

        return $config;
    }

    private function listFilterableColumns()
    {
        $indexes = $this->connection
            ->createQueryBuilder()
            ->select(['t.name as table_name', 'i.name as column_name'])
            ->from('information_schema.innodb_sys_indexes', 'i')
            ->innerJoin('i', 'information_schema.innodb_sys_tables', 't', 't.table_id = i.table_id')
            ->execute()
            ->fetchAll()
        ;

        foreach ($indexes as $index) {
            if (strpos($index['table_name'], '/') === false) {
                continue;
            }

            list($database, $table) = explode('/', $index['table_name']);

            if (!isset($this->filterable[$database])) {
                $this->filterable[$database] = [];
            }

            if (!isset($this->filterable[$database][$table])) {
                $this->filterable[$database][$table] = [];
            }

            $this->filterable[$database][$table][] = $index['column_name'];
        }
    }

    private function avoidPhpCollision(string $name)
    {
        return in_array(strtolower($name), ['interface'])
            ? $name . 's'
            : $name;
    }
}
