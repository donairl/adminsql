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
     * Show all tables in current database
     *
     * @return array
     */
    public function show_table() {
        if (!$this->connection) {
            throw new \Exception("No active database connection");
        }

        $query = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name";
        $result = pg_query($this->connection, $query);

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
     * Show fields/columns for a specific table
     *
     * @param string $table
     * @return array
     */
    public function show_field($table) {
        if (!$this->connection) {
            throw new \Exception("No active database connection");
        }

        if (empty($table)) {
            throw new \Exception("Table name cannot be empty");
        }

        $query = "SELECT column_name, data_type, is_nullable, column_default
                  FROM information_schema.columns
                  WHERE table_name = $1 AND table_schema = 'public'
                  ORDER BY ordinal_position";

        $result = pg_query_params($this->connection, $query, [$table]);

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
     * Run a custom SQL query and return rows
     *
     * @param string $query
     * @param array $params Optional parameters for parameterized queries
     * @return array
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
