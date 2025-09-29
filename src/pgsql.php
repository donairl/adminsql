<?php

namespace App;

class Pgsql {
    private $connection;

    /**
     * Connect to PostgreSQL database
     *
     * @param string $host
     * @param int $port
     * @param string $dbname
     * @param string $user
     * @param string $password
     * @return bool
     */
    public function connect($host = 'localhost', $port = 5432, $dbname = 'postgres', $user = 'postgres', $password = '') {
        $connectionString = "host={$host} port={$port} dbname={$dbname} user={$user} password={$password}";

        $this->connection = pg_connect($connectionString);

        if (!$this->connection) {
            throw new \Exception("Failed to connect to PostgreSQL: " . pg_last_error());
        }

        return true;
    }

    /**
     * Show all databases
     *
     * @return array
     */
    public function show_database() {
        if (!$this->connection) {
            throw new \Exception("No active database connection");
        }

        $query = "SELECT datname FROM pg_database WHERE datistemplate = false ORDER BY datname";
        $result = pg_query($this->connection, $query);

        if (!$result) {
            throw new \Exception("Failed to execute query: " . pg_last_error($this->connection));
        }

        $databases = [];
        while ($row = pg_fetch_assoc($result)) {
            $databases[] = $row['datname'];
        }

        pg_free_result($result);
        return $databases;
    }

    /**
     * Show all schemas in current database
     *
     * @return array
     */
    public function show_schemas() {
        if (!$this->connection) {
            throw new \Exception("No active database connection");
        }

        $query = "SELECT schema_name FROM information_schema.schemata
                  WHERE schema_name NOT LIKE 'pg_%'
                  AND schema_name != 'information_schema'
                  ORDER BY schema_name";
        $result = pg_query($this->connection, $query);

        if (!$result) {
            throw new \Exception("Failed to execute query: " . pg_last_error($this->connection));
        }

        $schemas = [];
        while ($row = pg_fetch_assoc($result)) {
            $schemas[] = $row['schema_name'];
        }

        pg_free_result($result);
        return $schemas;
    }

    /**
     * Show all tables in current database (public schema by default)
     *
     * @param string $schema Optional schema name, defaults to 'public'
     * @return array
     */
    public function show_table($schema = 'public') {
        if (!$this->connection) {
            throw new \Exception("No active database connection");
        }

        $query = "SELECT table_name FROM information_schema.tables
                  WHERE table_schema = $1
                  ORDER BY table_name";

        $result = pg_query_params($this->connection, $query, [$schema]);

        if (!$result) {
            throw new \Exception("Failed to execute query: " . pg_last_error($this->connection));
        }

        $tables = [];
        while ($row = pg_fetch_assoc($result)) {
            $tables[] = $row['table_name'];
        }

        pg_free_result($result);
        return $tables;
    }

    /**
     * Show all tables in a specific schema
     *
     * @param string $schema
     * @return array
     */
    public function show_tables_in_schema($schema) {
        return $this->show_table($schema);
    }

    /**
     * Show fields/columns for a specific table
     *
     * @param string $table
     * @param string $schema Optional schema name, defaults to 'public'
     * @return array
     */
    public function show_field($table, $schema = 'public') {
        if (!$this->connection) {
            throw new \Exception("No active database connection");
        }

        if (empty($table)) {
            throw new \Exception("Table name cannot be empty");
        }

        $query = "SELECT column_name, data_type, is_nullable, column_default
                  FROM information_schema.columns
                  WHERE table_name = $1 AND table_schema = $2
                  ORDER BY ordinal_position";

        $result = pg_query_params($this->connection, $query, [$table, $schema]);

        if (!$result) {
            throw new \Exception("Failed to execute query: " . pg_last_error($this->connection));
        }

        $fields = [];
        while ($row = pg_fetch_assoc($result)) {
            $fields[] = [
                'name' => $row['column_name'],
                'type' => $row['data_type'],
                'nullable' => $row['is_nullable'] === 'YES',
                'default' => $row['column_default']
            ];
        }

        pg_free_result($result);
        return $fields;
    }

    /**
     * Create a new schema
     *
     * @param string $schemaName
     * @return bool
     */
    public function create_schema($schemaName) {
        if (!$this->connection) {
            throw new \Exception("No active database connection");
        }

        if (empty($schemaName)) {
            throw new \Exception("Schema name cannot be empty");
        }

        $query = "CREATE SCHEMA " . pg_escape_identifier($this->connection, $schemaName);
        $result = pg_query($this->connection, $query);

        if (!$result) {
            throw new \Exception("Failed to create schema: " . pg_last_error($this->connection));
        }

        return true;
    }

    /**
     * Drop a schema
     *
     * @param string $schemaName
     * @param bool $cascade Whether to use CASCADE option
     * @return bool
     */
    public function drop_schema($schemaName, $cascade = false) {
        if (!$this->connection) {
            throw new \Exception("No active database connection");
        }

        if (empty($schemaName)) {
            throw new \Exception("Schema name cannot be empty");
        }

        $query = "DROP SCHEMA " . pg_escape_identifier($this->connection, $schemaName);
        if ($cascade) {
            $query .= " CASCADE";
        }

        $result = pg_query($this->connection, $query);

        if (!$result) {
            throw new \Exception("Failed to drop schema: " . pg_last_error($this->connection));
        }

        return true;
    }

    /**
     * Rename a schema
     *
     * @param string $oldName
     * @param string $newName
     * @return bool
     */
    public function rename_schema($oldName, $newName) {
        if (!$this->connection) {
            throw new \Exception("No active database connection");
        }

        if (empty($oldName) || empty($newName)) {
            throw new \Exception("Schema names cannot be empty");
        }

        $query = "ALTER SCHEMA " . pg_escape_identifier($this->connection, $oldName) .
                 " RENAME TO " . pg_escape_identifier($this->connection, $newName);

        $result = pg_query($this->connection, $query);

        if (!$result) {
            throw new \Exception("Failed to rename schema: " . pg_last_error($this->connection));
        }

        return true;
    }

    /**
     * Run a custom SQL query and return rows
     *
     * @param string $query
     * @param array $params Optional parameters for parameterized queries
     * @return array|resource
     */
    public function run_query($query, $params = []) {
        if (!$this->connection) {
            throw new \Exception("No active database connection");
        }

        if (empty($query)) {
            throw new \Exception("Query cannot be empty");
        }

        // Use parameterized query if params provided, otherwise regular query
        if (!empty($params)) {
            $result = pg_query_params($this->connection, $query, $params);
        } else {
            $result = pg_query($this->connection, $query);
        }

        if (!$result) {
            throw new \Exception("Failed to execute query: " . pg_last_error($this->connection));
        }

        // For non-SELECT queries, return the result resource directly
        if (stripos(trim($query), 'SELECT') !== 0) {
            return $result;
        }

        $rows = [];
        while ($row = pg_fetch_assoc($result)) {
            $rows[] = $row;
        }

        pg_free_result($result);
        return $rows;
    }

    /**
     * Close the database connection
     *
     * @return bool
     */
    public function close_connection() {
        if ($this->connection) {
            $result = pg_close($this->connection);
            $this->connection = null;
            return $result;
        }
        return true;
    }

    /**
     * Get the current connection resource
     *
     * @return resource|null
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Destructor to ensure connection is closed
     */
    public function __destruct() {
        $this->close_connection();
    }
}

?>