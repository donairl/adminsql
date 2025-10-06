<?php namespace App;

class Login {
    private $pgsql;
    private $message = '';
    private $databases = [];

    public function __construct() {
      
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->pgsql = new Pgsql();
    }

    /**
     * Process login form submission
     */
    public function processLogin() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $host = $_POST['host'] ?? 'localhost';
        $port = (int)($_POST['port'] ?? 5432);
        $database = $_POST['database'] ?? 'postgres';

        if (empty($username) || empty($password)) {
            $this->message = '<div class="alert alert-error">Username and password are required</div>';
            return;
        }

        try {
            $this->pgsql->connect($host, $port, $database, $username, $password);

            // Store connection details in session
            $_SESSION['host'] = $host;
            $_SESSION['port'] = $port;
            $_SESSION['database'] = $database;
            $_SESSION['username'] = $username;
            $_SESSION['password'] = $password;
            $_SESSION['logged_in'] = true;

            // Redirect to main dashboard
            header('Location: main.php');
            exit;

        } catch (Exception $e) {
            $this->message = '<div class="alert alert-error">Login failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    /**
     * Get the HTML for the login page
     */
    public function getHtml() {
        return $this->getHtmlHead() . $this->getHtmlBody() . $this->getHtmlFooter();
    }

    /**
     * Get HTML head section
     */
    private function getHtmlHead() {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PostgreSQL Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .login-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        input[type="text"], input[type="password"], input[type="number"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .row {
            display: flex;
            gap: 10px;
        }
        .row .form-group {
            flex: 1;
        }
        .btn {
            background-color: #007cba;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        .btn:hover {
            background-color: #005a87;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .database-info {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        .database-info h3 {
            margin-top: 0;
            color: #495057;
        }
        .database-info ul {
            margin: 10px 0 0 0;
            padding-left: 20px;
        }
        .database-info li {
            margin-bottom: 5px;
        }
    </style>
</head>';
    }

    /**
     * Get HTML body section
     */
    private function getHtmlBody() {
        return '<body>
    <div class="login-form">
        <h2>PostgreSQL Login</h2>

        ' . $this->message . '

        <form method="POST" action="">
            <div class="form-group">
                <label for="host">Host:</label>
                <input type="text" id="host" name="host" value="localhost" required>
            </div>

            <div class="row">
                <div class="form-group">
                    <label for="port">Port:</label>
                    <input type="number" id="port" name="port" value="5432" required>
                </div>
                <div class="form-group">
                    <label for="database">Database:</label>
                    <input type="text" id="database" name="database" value="postgres" required>
                </div>
            </div>

            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn">Connect to PostgreSQL</button>
        </form>
    </div>
</body>';
    }

    /**
     * Get HTML footer section
     */
    private function getHtmlFooter() {
        return '</html>';
    }

    /**
     * Clean up resources
     */
    public function __destruct() {
        if ($this->pgsql) {
            $this->pgsql->close_connection();
        }
    }
}



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



use App\Login;

// Create and run the login application
$login = new Login();
$login->processLogin();
echo $login->getHtml();
?>

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session data
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: index.php');
exit;


// Start session if not already started - MUST be first, before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in - redirect if not authenticated
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

namespace App;

class MainController
{
    private $pgsql;
    private $selectedDatabase;
    private $selectedSchema;
    private $selectedTable;
    private $selectedAction;
    private $selectedSubAction;
    private $currentPage;
    private $perPage;
    private $tableStructure;
    private $tableData;
    private $databases;
    private $schemas;
    private $tables;
    private $insertError;
    private $insertSuccess;
    private $deleteError;
    private $deleteSuccessCount;
    private $structureMessage;
    private $editRowData;
    private $editSuccess;
    private $editError;
    private $csvImportSuccess;
    private $csvImportError;
    private $csvExportSuccess;
    private $csvExportError;

    public function __construct($connectionData)
    {
        $this->pgsql = new Pgsql();

        // Initialize properties from URL parameters or defaults
        $this->selectedDatabase = $_GET['db'] ?? $connectionData['database'] ?? 'postgres';
        $this->selectedSchema = $_GET['schema'] ?? 'public';
        $this->selectedTable = $_GET['table'] ?? null;
        $this->selectedAction = $_GET['action'] ?? 'select';
        $this->selectedSubAction = $_GET['subaction'] ?? null;
        $this->currentPage = (int)($_GET['page'] ?? 1);
        $this->perPage = 25;

        // Initialize success/error states from URL parameters
        $this->insertSuccess = isset($_GET['inserted']);
        $this->deleteSuccessCount = isset($_GET['deleted']) ? (int)$_GET['deleted'] : 0;
        $this->editSuccess = isset($_GET['edited']);

        $this->insertError = null;
        $this->deleteError = null;
        $this->editError = null;
        $this->structureMessage = null;
        $this->csvImportSuccess = false;
        $this->csvImportError = null;
        $this->csvExportSuccess = false;
        $this->csvExportError = null;

        // Load initial data
        $this->loadDatabases();
        if ($this->selectedDatabase) {
            $this->loadSchemas();
            $this->loadTables();
        }

        if ($this->selectedTable) {
            $this->loadTableStructure();
            $this->loadTableAction();
        }
    }

    public function getSelectedDatabase() { return $this->selectedDatabase; }
    public function getSelectedSchema() { return $this->selectedSchema; }
    public function getSelectedTable() { return $this->selectedTable; }
    public function getSelectedAction() { return $this->selectedAction; }
    public function getSelectedSubAction() { return $this->selectedSubAction; }
    public function getCurrentPage() { return $this->currentPage; }
    public function getPerPage() { return $this->perPage; }
    public function getTableStructure() { return $this->tableStructure; }
    public function getTableData() { return $this->tableData; }
    public function getDatabases() { return $this->databases; }
    public function getSchemas() { return $this->schemas; }
    public function getTables() { return $this->tables; }
    public function getInsertError() { return $this->insertError ?? ''; }
    public function getInsertSuccess() { return $this->insertSuccess; }
    public function getDeleteError() { return $this->deleteError ?? ''; }
    public function getDeleteSuccessCount() { return $this->deleteSuccessCount; }
    public function getStructureMessage() { return $this->structureMessage; }
    public function getEditRowData() { return $this->editRowData; }
    public function getEditSuccess() { return $this->editSuccess; }
    public function getEditError() { return $this->editError ?? ''; }
    public function getCsvImportSuccess() { return $this->csvImportSuccess; }
    public function getCsvImportError() { return $this->csvImportError ?? ''; }
    public function getCsvExportSuccess() { return $this->csvExportSuccess; }
    public function getCsvExportError() { return $this->csvExportError ?? ''; }

    private function loadDatabases()
    {
        try {
            $this->pgsql->connect(
                $_SESSION['host'] ?? 'localhost',
                $_SESSION['port'] ?? 5432,
                'postgres',
                $_SESSION['username'],
                $_SESSION['password']
            );

            $this->databases = $this->pgsql->show_database();
        } catch (\Exception $e) {
            $this->databases = [];
        }
    }

    private function loadSchemas()
    {
        if (!$this->selectedDatabase) return;

        try {
            $this->pgsql->connect(
                $_SESSION['host'] ?? 'localhost',
                $_SESSION['port'] ?? 5432,
                $this->selectedDatabase,
                $_SESSION['username'],
                $_SESSION['password']
            );

            $this->schemas = $this->pgsql->show_schemas();
        } catch (\Exception $e) {
            $this->schemas = [];
        }
    }

    private function loadTables()
    {
        if (!$this->selectedDatabase || !$this->selectedSchema) return;

        try {
            $this->pgsql->connect(
                $_SESSION['host'] ?? 'localhost',
                $_SESSION['port'] ?? 5432,
                $this->selectedDatabase,
                $_SESSION['username'],
                $_SESSION['password']
            );

            $this->tables = $this->pgsql->show_table($this->selectedSchema);
        } catch (\Exception $e) {
            $this->tables = [];
        }
    }

    private function loadTableStructure()
    {
        if (!$this->selectedTable) return;

        try {
            $this->pgsql->connect(
                $_SESSION['host'] ?? 'localhost',
                $_SESSION['port'] ?? 5432,
                $this->selectedDatabase,
                $_SESSION['username'],
                $_SESSION['password']
            );

            $this->tableStructure = $this->pgsql->show_field($this->selectedTable, $this->selectedSchema);
        } catch (\Exception $e) {
            $this->tableStructure = [];
        }
    }

    private function loadTableAction()
    {
        if (!$this->selectedTable) return;

        switch ($this->selectedAction) {
            case 'select':
                $this->loadTableData();
                break;
            case 'structure':
                // Structure is already loaded in loadTableStructure()
                break;
            case 'insert':
                // No additional loading needed for insert form
                break;
            case 'delete':
                // Load table data for delete confirmation UI
                $this->loadTableData();
                break;
        }
    }

    private function loadTableData()
    {
        if (!$this->selectedTable) return;

        try {
            $this->pgsql->connect(
                $_SESSION['host'] ?? 'localhost',
                $_SESSION['port'] ?? 5432,
                $this->selectedDatabase,
                $_SESSION['username'],
                $_SESSION['password']
            );

            $offset = ($this->currentPage - 1) * $this->perPage;
            $query = "SELECT * FROM " . $this->selectedTable .
                    " ORDER BY 1 LIMIT $this->perPage OFFSET $offset";

            $this->tableData = $this->pgsql->run_query($query);
        } catch (\Exception $e) {
            $this->tableData = [];
        }
    }

    // Add method to load data for a specific row for editing
    public function loadEditRowData($rowId, $primaryKeyColumn)
    {
        if (!$this->selectedTable) return;

        try {
            $this->pgsql->connect(
                $_SESSION['host'] ?? 'localhost',
                $_SESSION['port'] ?? 5432,
                $this->selectedDatabase,
                $_SESSION['username'],
                $_SESSION['password']
            );

            $query = "SELECT * FROM " . $this->selectedTable .
                    " WHERE " . $primaryKeyColumn . " = $1";

            $result = $this->pgsql->run_query($query, [$rowId]);
            
            if (!empty($result)) {
                $this->editRowData = $result[0];
            } else {
                $this->editRowData = null;
            }
        } catch (\Exception $e) {
            $this->editRowData = null;
        }
    }

    public function processInsertForm()
    {
        try {
            // Ensure we're connected to the correct database
            $this->pgsql->connect(
                $_SESSION['host'] ?? 'localhost',
                $_SESSION['port'] ?? 5432,
                $this->selectedDatabase,
                $_SESSION['username'],
                $_SESSION['password']
            );

            $columns = [];
            $values = [];
            $placeholders = [];
            $paramIndex = 1;

            foreach ($this->tableStructure as $index => $column) {
                $fieldName = $column['name'];
                $fieldType = strtolower($column['type']);
                $isSerial = $this->isSerialField($fieldType);
                $isIdField = $this->isIdField($fieldName);

                // Handle auto-increment fields (ID fields or SERIAL fields)
                if ($isIdField || $isSerial) {
                    $mode = $_POST[$fieldName . '_mode'] ?? 'auto';
                    if ($mode === 'auto') {
                        // Skip auto-increment fields
                        continue;
                    }
                    // For manual mode, continue with normal processing
                }

                // Handle NULL values - use NULL literal, no parameter needed
                if (isset($_POST[$fieldName . '_null']) && $_POST[$fieldName . '_null'] === '1') {
                    $columns[] = $fieldName;
                    $placeholders[] = 'NULL';
                } elseif (isset($_POST[$fieldName])) {
                    $value = $_POST[$fieldName];

                    // Handle checkbox values
                    if ($fieldType === 'boolean') {
                        $value = $value ? 'true' : 'false';
                    }

                    $columns[] = $fieldName;
                    $values[] = $value;
                    $placeholders[] = '$' . $paramIndex;
                    $paramIndex++;
                }
            }

            if (empty($columns)) {
                throw new \Exception('No valid fields to insert');
            }

            $query = "INSERT INTO " . $this->selectedTable .
                    " (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

            $result = $this->pgsql->run_query($query, $values);

            // Set success flag instead of redirecting
            $this->insertSuccess = true;
            return true;

        } catch (\Exception $e) {
            $this->insertError = $e->getMessage();
            return false;
        }
    }

    /**
     * Process CSV import
     */
    public function processCsvImport()
    {
        try {
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception('No file uploaded or upload error');
            }

            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, 'r');
            
            if (!$handle) {
                throw new \Exception('Failed to open uploaded file');
            }

            // Ensure we're connected to the correct database
            $this->pgsql->connect(
                $_SESSION['host'] ?? 'localhost',
                $_SESSION['port'] ?? 5432,
                $this->selectedDatabase,
                $_SESSION['username'],
                $_SESSION['password']
            );

            // Get column headers from CSV
            $headers = fgetcsv($handle);
            
            if (!$headers) {
                throw new \Exception('CSV file is empty or invalid');
            }

            // Prepare for batch insert
            $columns = [];
            foreach ($headers as $header) {
                $columns[] = '"' . str_replace('"', '""', trim($header)) . '"';
            }
            
            $columnList = implode(', ', $columns);
            $importedRows = 0;

            // Process each row
            while (($row = fgetcsv($handle)) !== false) {
                // Prepare placeholders for values
                $placeholders = [];
                $values = [];
                
                for ($i = 0; $i < count($row); $i++) {
                    $placeholders[] = '$' . ($i + 1);
                    $values[] = $row[$i];
                }
                
                $placeholderList = implode(', ', $placeholders);
                
                // Build and execute insert query
                $query = "INSERT INTO " . $this->selectedTable .
                        " ($columnList) VALUES ($placeholderList)";
                
                $result = $this->pgsql->run_query($query, $values);
                $importedRows++;
            }
            
            fclose($handle);
            
            $this->csvImportSuccess = true;
            return true;

        } catch (\Exception $e) {
            $this->csvImportError = $e->getMessage();
            return false;
        }
    }

    /**
     * Process CSV export
     */
    public function processCsvExport()
    {
        try {
            // Ensure we're connected to the correct database
            $this->pgsql->connect(
                $_SESSION['host'] ?? 'localhost',
                $_SESSION['port'] ?? 5432,
                $this->selectedDatabase,
                $_SESSION['username'],
                $_SESSION['password']
            );

            // Get all data from the table
            $query = "SELECT * FROM " . $this->selectedTable;
            $result = $this->pgsql->run_query($query);

            if (empty($result)) {
                throw new \Exception('No data to export');
            }

            // Set headers for CSV download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $this->selectedTable . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');

            // Output CSV directly to browser
            $output = fopen('php://output', 'w');
            
            // Write column headers
            if (!empty($result)) {
                $headers = array_keys($result[0]);
                fputcsv($output, $headers);
                
                // Write data rows
                foreach ($result as $row) {
                    fputcsv($output, $row);
                }
            }
            
            fclose($output);
            
            // Exit to prevent further output
            exit;

        } catch (\Exception $e) {
            $this->csvExportError = $e->getMessage();
            return false;
        }
    }

    // Add method to process edit form
    public function processEditForm()
    {
        try {
            // Ensure we're connected to the correct database
            $this->pgsql->connect(
                $_SESSION['host'] ?? 'localhost',
                $_SESSION['port'] ?? 5432,
                $this->selectedDatabase,
                $_SESSION['username'],
                $_SESSION['password']
            );

            // Get the primary key column
            $primaryKeyColumn = null;
            foreach ($this->tableStructure as $column) {
                if (isset($column['primary_key']) && $column['primary_key']) {
                    $primaryKeyColumn = $column['name'];
                    break;
                }
            }

            // Fallback: look for common ID field names
            if (!$primaryKeyColumn) {
                $idFields = ['id', 'pk', 'primary_key', 'key'];
                foreach ($this->tableStructure as $column) {
                    if (in_array(strtolower($column['name']), $idFields)) {
                        $primaryKeyColumn = $column['name'];
                        break;
                    }
                }
            }

            // Last resort: use first column
            if (!$primaryKeyColumn && !empty($this->tableStructure)) {
                $primaryKeyColumn = $this->tableStructure[0]['name'];
            }

            if (!$primaryKeyColumn) {
                throw new \Exception('Cannot determine primary key for update');
            }

            // Get the row ID from POST data
            $rowId = $_POST['row_id'] ?? null;
            if (!$rowId) {
                throw new \Exception('No row ID provided for update');
            }

            $columns = [];
            $values = [];
            $paramIndex = 1;

            foreach ($this->tableStructure as $index => $column) {
                $fieldName = $column['name'];
                
                // Skip the primary key column as it's used in WHERE clause
                if ($fieldName === $primaryKeyColumn) {
                    continue;
                }

                // Handle NULL values
                if (isset($_POST[$fieldName . '_null']) && $_POST[$fieldName . '_null'] === '1') {
                    $columns[] = $fieldName . " = NULL";
                } elseif (isset($_POST[$fieldName])) {
                    $value = $_POST[$fieldName];
                    $fieldType = strtolower($column['type']);

                    // Handle checkbox values
                    if ($fieldType === 'boolean') {
                        $value = $value ? 'true' : 'false';
                    }

                    $columns[] = $fieldName . " = $" . $paramIndex;
                    $values[] = $value;
                    $paramIndex++;
                }
            }

            if (empty($columns)) {
                throw new \Exception('No valid fields to update');
            }

            // Add the row ID as the last parameter for WHERE clause
            $values[] = $rowId;

            $query = "UPDATE " . $this->selectedTable .
                    " SET " . implode(', ', $columns) .
                    " WHERE " . $primaryKeyColumn . " = $" . $paramIndex;

            $result = $this->pgsql->run_query($query, $values);

            // Set success flag
            $this->editSuccess = true;
            return true;

        } catch (\Exception $e) {
            $this->editError = $e->getMessage();
            return false;
        }
    }

    public function processDeleteRows()
    {
        try {
            // Ensure we're connected to the correct database
            $this->pgsql->connect(
                $_SESSION['host'] ?? 'localhost',
                $_SESSION['port'] ?? 5432,
                $this->selectedDatabase,
                $_SESSION['username'],
                $_SESSION['password']
            );

            if (!isset($_POST['selected_rows']) || empty($_POST['selected_rows'])) {
                throw new \Exception('No rows selected for deletion');
            }

            $deletedCount = 0;
            $selectedRows = $_POST['selected_rows'];

            // Try to find primary key column for WHERE clause
            $primaryKeyColumn = null;
            foreach ($this->tableStructure as $column) {
                if (isset($column['primary_key']) && $column['primary_key']) {
                    $primaryKeyColumn = $column['name'];
                    break;
                }
            }

            // Fallback: look for common ID field names
            if (!$primaryKeyColumn) {
                $idFields = ['id', 'pk', 'primary_key', 'key'];
                foreach ($this->tableStructure as $column) {
                    if (in_array(strtolower($column['name']), $idFields)) {
                        $primaryKeyColumn = $column['name'];
                        break;
                    }
                }
            }

            // Last resort: use first column
            if (!$primaryKeyColumn && !empty($this->tableStructure)) {
                $primaryKeyColumn = $this->tableStructure[0]['name'];
            }

            if (!$primaryKeyColumn) {
                throw new \Exception('Cannot determine primary key for deletion');
            }

            foreach ($selectedRows as $rowId) {
                try {
                    $query = "DELETE FROM " . $this->selectedTable .
                            " WHERE " . $primaryKeyColumn . " = $1";

                    $result = $this->pgsql->run_query($query, [$rowId]);
                    $deletedCount++;
                } catch (\Exception $e) {
                    // Continue with other deletions even if one fails
                    error_log("Failed to delete row with ID $rowId: " . $e->getMessage());
                }
            }

            if ($deletedCount > 0) {
                // Set success count instead of redirecting
                $this->deleteSuccessCount = $deletedCount;
                return true;
            } else {
                throw new \Exception('No rows were deleted');
            }

        } catch (\Exception $e) {
            $this->deleteError = $e->getMessage();
            return false;
        }
    }

    public function processDeleteAllRecords()
    {
        try {
            // Ensure we're connected to the correct database
            $this->pgsql->connect(
                $_SESSION['host'] ?? 'localhost',
                $_SESSION['port'] ?? 5432,
                $this->selectedDatabase,
                $_SESSION['username'],
                $_SESSION['password']
            );

            if (!$this->selectedTable) {
                throw new \Exception('No table selected for deletion');
            }

            // Get row count before deletion for confirmation
            $countQuery = "SELECT COUNT(*) as total FROM " . $this->selectedTable;
            $countResult = $this->pgsql->run_query($countQuery);
            
            // Since this is a SELECT query, $countResult should be an array
            $rowCount = $countResult[0]['total'] ?? 0;

            // Execute DELETE ALL query
            $query = "DELETE FROM " . $this->selectedTable;
            $result = $this->pgsql->run_query($query);

            // Get affected row count
            $affectedRows = 0; // Simplified for now

            if ($affectedRows >= 0) {
                $this->deleteSuccessCount = $affectedRows;
                return true;
            } else {
                throw new \Exception('Failed to delete records');
            }

        } catch (\Exception $e) {
            $this->deleteError = $e->getMessage();
            return false;
        }
    }

    public function processDropTable()
    {
        try {
            // Ensure we're connected to the correct database
            $this->pgsql->connect(
                $_SESSION['host'] ?? 'localhost',
                $_SESSION['port'] ?? 5432,
                $this->selectedDatabase,
                $_SESSION['username'],
                $_SESSION['password']
            );

            if (!isset($_POST['table_name']) || empty($_POST['table_name'])) {
                throw new \Exception('No table name provided');
            }

            $tableName = trim($_POST['table_name']);

            // Execute DROP TABLE query
            $query = "DROP TABLE " . $tableName;
            $result = $this->pgsql->run_query($query);

            // Set success message
            $_SESSION['drop_success'] = "Table '" . htmlspecialchars($tableName) . "' has been successfully dropped.";
            return true;

        } catch (\Exception $e) {
            $_SESSION['drop_error'] = $e->getMessage();
            return false;
        }
    }

    public function processCreateTable()
    {
        try {
            // Ensure we're connected to the correct database
            $this->pgsql->connect(
                $_SESSION['host'] ?? 'localhost',
                $_SESSION['port'] ?? 5432,
                $this->selectedDatabase,
                $_SESSION['username'],
                $_SESSION['password']
            );

            if (!isset($_POST['table_name']) || empty(trim($_POST['table_name']))) {
                throw new \Exception('Table name is required');
            }

            if (!isset($_POST['columns']) || empty($_POST['columns'])) {
                throw new \Exception('At least one column is required');
            }

            $tableName = trim($_POST['table_name']);
            $columns = $_POST['columns'];

            // Validate table name
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
                throw new \Exception('Invalid table name. Use only letters, numbers, and underscores, starting with a letter or underscore.');
            }

            // Build CREATE TABLE SQL
            $sql = "CREATE TABLE " . $tableName . " (\n";

            $columnDefinitions = [];
            $primaryKeys = [];

            foreach ($columns as $column) {
                if (empty($column['name']) || empty($column['type'])) {
                    continue; // Skip empty columns
                }

                $columnName = trim($column['name']);
                $columnType = $column['type'];

                // Validate column name
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $columnName)) {
                    throw new \Exception("Invalid column name '$columnName'. Use only letters, numbers, and underscores.");
                }

                $definition = $columnName . " " . $columnType;

                // Add constraints
                if (isset($column['not_null']) && $column['not_null']) {
                    $definition .= " NOT NULL";
                }

                if (isset($column['unique']) && $column['unique']) {
                    $definition .= " UNIQUE";
                }

                if (isset($column['primary']) && $column['primary']) {
                    $primaryKeys[] = $columnName;
                }

                $columnDefinitions[] = $definition;
            }

            if (empty($columnDefinitions)) {
                throw new \Exception('At least one valid column is required');
            }

            // Add primary key constraint if specified
            if (!empty($primaryKeys)) {
                $columnDefinitions[] = "PRIMARY KEY (" . implode(', ', $primaryKeys) . ")";
            }

            $sql .= implode(",\n", $columnDefinitions) . "\n)";

            // Execute the CREATE TABLE query
            $result = $this->pgsql->run_query($sql);

            // Set success message
            $_SESSION['create_success'] = "Table '" . htmlspecialchars($tableName) . "' has been created successfully with " . count($columnDefinitions) . " columns.";
            return true;

        } catch (\Exception $e) {
            $_SESSION['create_error'] = $e->getMessage();
            return false;
        }
    }

    public function isSerialField($type)
    {
        $type = strtolower($type);
        return strpos($type, 'serial') !== false || $type === 'bigserial' || $type === 'smallserial';
    }

    public function isIdField($name)
    {
        $name = strtolower($name);
        return $name === 'id' || strpos($name, '_id') === 0 || strpos($name, 'id_') !== false;
    }

    public function processCreateSchema()
    {
        try {
            // Ensure we're connected to the correct database
            $this->pgsql->connect(
                $_SESSION['host'] ?? 'localhost',
                $_SESSION['port'] ?? 5432,
                $this->selectedDatabase,
                $_SESSION['username'],
                $_SESSION['password']
            );

            if (!isset($_POST['schema_name']) || empty(trim($_POST['schema_name']))) {
                throw new \Exception('Schema name is required');
            }

            $schemaName = trim($_POST['schema_name']);

            // Validate schema name (basic validation)
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $schemaName)) {
                throw new \Exception('Invalid schema name. Schema names must start with a letter or underscore and contain only letters, numbers, and underscores.');
            }

            // Create the schema
            $this->pgsql->create_schema($schemaName);

            // Set success message
            $_SESSION['schema_success'] = "Schema '" . htmlspecialchars($schemaName) . "' has been successfully created.";
            return true;

        } catch (\Exception $e) {
            $_SESSION['schema_error'] = $e->getMessage();
            return false;
        }
    }

    public function processDropSchema()
    {
        try {
            // Ensure we're connected to the correct database
            $this->pgsql->connect(
                $_SESSION['host'] ?? 'localhost',
                $_SESSION['port'] ?? 5432,
                $this->selectedDatabase,
                $_SESSION['username'],
                $_SESSION['password']
            );

            if (!isset($_POST['schema_name']) || empty($_POST['schema_name'])) {
                throw new \Exception('No schema name provided');
            }

            $schemaName = trim($_POST['schema_name']);
            $cascade = isset($_POST['cascade']) && $_POST['cascade'] === '1';

            // Prevent dropping system schemas
            $systemSchemas = ['public', 'information_schema'];
            if (in_array(strtolower($schemaName), $systemSchemas)) {
                throw new \Exception('Cannot drop system schema: ' . htmlspecialchars($schemaName));
            }

            // Drop the schema
            $this->pgsql->drop_schema($schemaName, $cascade);

            // Set success message
            $_SESSION['schema_success'] = "Schema '" . htmlspecialchars($schemaName) . "' has been successfully dropped.";
            return true;

        } catch (\Exception $e) {
            $_SESSION['schema_error'] = $e->getMessage();
            return false;
        }
    }

    public function processRenameSchema()
    {
        try {
            // Ensure we're connected to the correct database
            $this->pgsql->connect(
                $_SESSION['host'] ?? 'localhost',
                $_SESSION['port'] ?? 5432,
                $this->selectedDatabase,
                $_SESSION['username'],
                $_SESSION['password']
            );

            if (!isset($_POST['old_schema_name']) || empty(trim($_POST['old_schema_name']))) {
                throw new \Exception('Current schema name is required');
            }

            if (!isset($_POST['new_schema_name']) || empty(trim($_POST['new_schema_name']))) {
                throw new \Exception('New schema name is required');
            }

            $oldName = trim($_POST['old_schema_name']);
            $newName = trim($_POST['new_schema_name']);

            // Validate new schema name
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $newName)) {
                throw new \Exception('Invalid new schema name. Schema names must start with a letter or underscore and contain only letters, numbers, and underscores.');
            }

            // Prevent renaming system schemas
            $systemSchemas = ['public', 'information_schema'];
            if (in_array(strtolower($oldName), $systemSchemas)) {
                throw new \Exception('Cannot rename system schema: ' . htmlspecialchars($oldName));
            }

            // Rename the schema
            $this->pgsql->rename_schema($oldName, $newName);

            // Set success message
            $_SESSION['schema_success'] = "Schema '" . htmlspecialchars($oldName) . "' has been successfully renamed to '" . htmlspecialchars($newName) . "'.";
            return true;

        } catch (\Exception $e) {
            $_SESSION['schema_error'] = $e->getMessage();
            return false;
        }
    }

    public function processSqlQuery()
    {
        try {
            // Ensure we're connected to the correct database
            $this->pgsql->connect(
                $_SESSION['host'] ?? 'localhost',
                $_SESSION['port'] ?? 5432,
                $this->selectedDatabase,
                $_SESSION['username'],
                $_SESSION['password']
            );

            if (!isset($_POST['sql_query']) || empty(trim($_POST['sql_query']))) {
                throw new \Exception('No SQL query provided');
            }

            $query = trim($_POST['sql_query']);

            // Check if it's a SELECT query or other query type
            $isSelect = stripos($query, 'SELECT') === 0;

            if ($isSelect) {
                $result = $this->pgsql->run_query($query);
                $_SESSION['sql_result'] = ['rows' => $result];
            } else {
                // For non-SELECT queries, execute and get affected rows
                $result = $this->pgsql->run_query($query);
                
                // Get affected row count
                $affectedRows = 0; // Simplified for now
                
                $_SESSION['sql_result'] = ['rows' => [], 'affected_rows' => $affectedRows];
            }

            return true;

        } catch (\Exception $e) {
            $_SESSION['sql_result'] = ['error' => $e->getMessage()];
            return false;
        }
    }

    /**
     * Process add field form submission
     */
    public function processAddField()
    {
        try {
            // Ensure we're connected to the correct database
            $this->pgsql->connect(
                $_SESSION['host'] ?? 'localhost',
                $_SESSION['port'] ?? 5432,
                $this->selectedDatabase,
                $_SESSION['username'],
                $_SESSION['password']
            );

            if (!isset($_POST['field_name']) || empty(trim($_POST['field_name']))) {
                throw new \Exception('Field name is required');
            }

            if (!isset($_POST['field_type']) || empty(trim($_POST['field_type']))) {
                throw new \Exception('Field type is required');
            }

            $fieldName = trim($_POST['field_name']);
            $fieldType = trim($_POST['field_type']);
            $isNullable = isset($_POST['is_nullable']) && $_POST['is_nullable'] == '1';
            $defaultValue = isset($_POST['default_value']) ? trim($_POST['default_value']) : null;

            // Validate field name
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $fieldName)) {
                throw new \Exception('Invalid field name. Use only letters, numbers, and underscores, starting with a letter or underscore.');
            }

            // Build ALTER TABLE SQL
            $sql = "ALTER TABLE " . $this->selectedTable . 
                   " ADD COLUMN " . $fieldName . " " . $fieldType;

            // Add NULL/NOT NULL constraint
            if (!$isNullable) {
                $sql .= " NOT NULL";
            }

            // Add default value if provided
            if ($defaultValue !== null && $defaultValue !== '') {
                $sql .= " DEFAULT '" . $defaultValue . "'";
            }

            // Execute the ALTER TABLE query
            $result = $this->pgsql->run_query($sql);

            // Set success message
            $this->structureMessage = [
                'type' => 'success',
                'text' => "Field '" . htmlspecialchars($fieldName) . "' has been added successfully."
            ];
            return true;

        } catch (\Exception $e) {
            $this->structureMessage = [
                'type' => 'error',
                'text' => 'Add field failed: ' . $e->getMessage()
            ];
            return false;
        }
    }

    /**
     * Process edit field form submission
     */
    public function processEditField()
    {
        try {
            // Ensure we're connected to the correct database
            $this->pgsql->connect(
                $_SESSION['host'] ?? 'localhost',
                $_SESSION['port'] ?? 5432,
                $this->selectedDatabase,
                $_SESSION['username'],
                $_SESSION['password']
            );

            if (!isset($_POST['original_field_name']) || empty(trim($_POST['original_field_name']))) {
                throw new \Exception('Original field name is required');
            }

            if (!isset($_POST['field_name']) || empty(trim($_POST['field_name']))) {
                throw new \Exception('Field name is required');
            }

            if (!isset($_POST['field_type']) || empty(trim($_POST['field_type']))) {
                throw new \Exception('Field type is required');
            }

            $originalFieldName = trim($_POST['original_field_name']);
            $fieldName = trim($_POST['field_name']);
            $fieldType = trim($_POST['field_type']);
            $isNullable = isset($_POST['is_nullable']) && $_POST['is_nullable'] == '1';
            $defaultValue = isset($_POST['default_value']) ? trim($_POST['default_value']) : null;

            // Validate field name
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $fieldName)) {
                throw new \Exception('Invalid field name. Use only letters, numbers, and underscores, starting with a letter or underscore.');
            }

            // Check if field name is being changed
            if ($originalFieldName !== $fieldName) {
                // Rename the column first
                $renameSql = "ALTER TABLE " . $this->selectedTable . 
                             " RENAME COLUMN " . $originalFieldName . 
                             " TO " . $fieldName;
                $this->pgsql->run_query($renameSql);
            }

            // Change the column type
            $typeSql = "ALTER TABLE " . $this->selectedTable . 
                       " ALTER COLUMN " . $fieldName . 
                       " TYPE " . $fieldType;
            $this->pgsql->run_query($typeSql);

            // Set NULL/NOT NULL constraint
            $nullSql = "ALTER TABLE " . $this->selectedTable . 
                       " ALTER COLUMN " . $fieldName . 
                       ($isNullable ? " DROP NOT NULL" : " SET NOT NULL");
            $this->pgsql->run_query($nullSql);

            // Set or drop default value
            if ($defaultValue !== null && $defaultValue !== '') {
                $defaultSql = "ALTER TABLE " . $this->selectedTable . 
                              " ALTER COLUMN " . $fieldName . 
                              " SET DEFAULT '" . $defaultValue . "'";
                $this->pgsql->run_query($defaultSql);
            } else {
                $defaultSql = "ALTER TABLE " . $this->selectedTable . 
                              " ALTER COLUMN " . $fieldName . 
                              " DROP DEFAULT";
                $this->pgsql->run_query($defaultSql);
            }

            // Set success message
            $this->structureMessage = [
                'type' => 'success',
                'text' => "Field '" . htmlspecialchars($fieldName) . "' has been updated successfully."
            ];
            return true;

        } catch (\Exception $e) {
            $this->structureMessage = [
                'type' => 'error',
                'text' => 'Edit field failed: ' . $e->getMessage()
            ];
            return false;
        }
    }

    /**
     * Process delete field form submission
     */
    public function processDeleteField()
    {
        try {
            // Ensure we're connected to the correct database
            $this->pgsql->connect(
                $_SESSION['host'] ?? 'localhost',
                $_SESSION['port'] ?? 5432,
                $this->selectedDatabase,
                $_SESSION['username'],
                $_SESSION['password']
            );

            if (!isset($_POST['field_name']) || empty(trim($_POST['field_name']))) {
                throw new \Exception('Field name is required');
            }

            $fieldName = trim($_POST['field_name']);

            // Validate field name
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $fieldName)) {
                throw new \Exception('Invalid field name. Use only letters, numbers, and underscores, starting with a letter or underscore.');
            }

            // Build ALTER TABLE SQL to drop the column
            $sql = "ALTER TABLE " . $this->selectedTable . 
                   " DROP COLUMN " . $fieldName;

            // Execute the ALTER TABLE query
            $result = $this->pgsql->run_query($sql);

            // Set success message
            $this->structureMessage = [
                'type' => 'success',
                'text' => "Field '" . htmlspecialchars($fieldName) . "' has been deleted successfully."
            ];
            return true;

        } catch (\Exception $e) {
            $this->structureMessage = [
                'type' => 'error',
                'text' => 'Delete field failed: ' . $e->getMessage()
            ];
            return false;
        }
    }
}

class MainView
{
    private $controller;

    public function __construct(MainController $controller)
    {
        $this->controller = $controller;
    }

    public function getHtml()
    {
        $html = $this->getHtmlHead();
        $html .= $this->getHtmlBody();
        $html .= $this->getHtmlScripts();
        return $html;
    }

    private function getHtmlHead()
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PostgreSQL Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        ' . $this->getCss() . '
    </style>
</head>';
    }

    private function getHtmlBody()
    {
        return '<body>
    <div class="dashboard-container">
        ' . $this->getSidebar() . '
        <div class="main-content">
            <header class="dashboard-header">
                <h1><i class="fas fa-database"></i> PostgreSQL Dashboard</h1>
                <div class="header-actions">
                    <span class="user-info">Connected as: ' . htmlspecialchars($_SESSION['username'] ?? 'Unknown') . '</span>
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </header>
            <main class="dashboard-main">
                ' . $this->getMainContent() . '
            </main>
        </div>
    </div>

    <!-- Schema Management Modals -->
    <div id="createSchemaModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Create New Schema</h3>
                <span class="modal-close" onclick="closeSchemaModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_schema">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="schema_name">Schema Name:</label>
                        <input type="text" id="schema_name" name="schema_name" required
                               pattern="^[a-zA-Z_][a-zA-Z0-9_]*$"
                               title="Schema name must start with a letter or underscore and contain only letters, numbers, and underscores">
                        <small class="form-hint">Use only letters, numbers, and underscores. Must start with a letter or underscore.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeSchemaModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Create Schema</button>
                </div>
            </form>
        </div>
    </div>

    <div id="renameSchemaModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Rename Schema</h3>
                <span class="modal-close" onclick="closeSchemaModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="rename_schema">
                <input type="hidden" id="old_schema_name" name="old_schema_name">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="current_schema_display">Current Name:</label>
                        <input type="text" id="current_schema_display" readonly>
                    </div>
                    <div class="form-group">
                        <label for="new_schema_name">New Name:</label>
                        <input type="text" id="new_schema_name" name="new_schema_name" required
                               pattern="^[a-zA-Z_][a-zA-Z0-9_]*$"
                               title="Schema name must start with a letter or underscore and contain only letters, numbers, and underscores">
                        <small class="form-hint">Use only letters, numbers, and underscores. Must start with a letter or underscore.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeSchemaModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Rename Schema</button>
                </div>
            </form>
        </div>
    </div>

    <div id="dropSchemaModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Drop Schema</h3>
                <span class="modal-close" onclick="closeSchemaModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="drop_schema">
                <input type="hidden" id="drop_schema_name" name="schema_name">
                <div class="modal-body">
                    <div class="warning-message">
                        <p><strong>Warning:</strong> You are about to drop the schema "<span id="drop_schema_display"></span>".</p>
                        <p>This action cannot be undone and will delete all tables and data within this schema.</p>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="cascade_option" name="cascade" value="1">
                            Use CASCADE (also drop dependent objects)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeSchemaModal()">Cancel</button>
                    <button type="submit" class="btn-danger">Drop Schema</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>';
    }

    private function getSidebar()
    {
        $html = '<nav class="sidebar">
    <div class="sidebar-header">
        <h3>Databases</h3>
    </div>
    <div class="sidebar-content">';

        $databases = $this->controller->getDatabases();
        $selectedDb = $this->controller->getSelectedDatabase();

        foreach ($databases as $db) {
            $isActive = ($db === $selectedDb) ? 'active' : '';
            $html .= '<div class="database-item ' . $isActive . '">
                <a href="?db=' . urlencode($db) . '">
                    <i class="fas fa-database"></i> ' . htmlspecialchars($db) . '
                </a>';

            if ($db === $selectedDb) {
                $html .= $this->getSchemasList();
            }

            $html .= '</div>';
        }

        $html .= '</div></nav>';
        return $html;
    }

    private function getSchemasList()
    {
        $schemas = $this->controller->getSchemas();
        $selectedSchema = $this->controller->getSelectedSchema();

        if (empty($schemas)) return '';

        $html = '<ul class="schemas-list">';

        foreach ($schemas as $schema) {
            $isActive = ($schema === $selectedSchema) ? 'active' : '';
            $html .= '<li class="schema-item ' . $isActive . '">
                <a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . '&schema=' . urlencode($schema) . '" class="schema-name">
                    <i class="fas fa-folder"></i> ' . htmlspecialchars($schema) . '
                </a>';

            if ($schema === $selectedSchema) {
                $html .= '<ul class="schema-actions">
                    <li><a href="#" onclick="showCreateSchemaModal()" title="Create Schema"><i class="fas fa-plus"></i></a></li>
                    <li><a href="#" onclick="showRenameSchemaModal(\'' . htmlspecialchars($schema) . '\')" title="Rename Schema"><i class="fas fa-edit"></i></a></li>
                    <li><a href="#" onclick="showDropSchemaModal(\'' . htmlspecialchars($schema) . '\')" title="Drop Schema"><i class="fas fa-trash"></i></a></li>
                </ul>';

                $html .= $this->getTablesList();
            }

            $html .= '</li>';
        }

        $html .= '</ul>';
        return $html;
    }

    private function getTablesList()
    {
        $tables = $this->controller->getTables();
        $selectedTable = $this->controller->getSelectedTable();
        $selectedSchema = $this->controller->getSelectedSchema();

        if (empty($tables)) return '';

        $html = '<ul class="tables-list">';

        foreach ($tables as $table) {
            $isActive = ($table === $selectedTable) ? 'active' : '';
            $html .= '<li class="table-item ' . $isActive . '">
                <a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . '&schema=' . urlencode($selectedSchema) . '&table=' . urlencode($table) . '&action=select" class="table-name">
                    <i class="fas fa-table"></i> ' . htmlspecialchars($table) . '
                </a>';

            if ($table === $selectedTable) {
                //table actions
                $html .= '<ul class="table-actions">
                    <li><a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . '&schema=' . urlencode($selectedSchema) . '&table=' . urlencode($table) . '&action=structure"><i class="fas fa-info-circle"></i></a></li>
                    <li><a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . '&schema=' . urlencode($selectedSchema) . '&table=' . urlencode($table) . '&action=select"><i class="fas fa-list"></i></a></li>
                    <li><a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . '&schema=' . urlencode($selectedSchema) . '&table=' . urlencode($table) . '&action=insert"><i class="fas fa-plus"></i></a></li>
                    <li><a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . '&schema=' . urlencode($selectedSchema) . '&table=' . urlencode($table) . '&action=delete"><i class="fas fa-trash"></i></a></li>
                    <li><a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . '&schema=' . urlencode($selectedSchema) . '&table=' . urlencode($table) . '&action=drop" onclick="return confirmDropTable(\'' . htmlspecialchars($table) . '\')"><i class="fas fa-times-circle"></i></a></li>
                </ul>';
            }

            $html .= '</li>';
        }

        $html .= '</ul>';
        return $html;
    }

    private function getDatabaseOverview()
    {
        $selectedDb = $this->controller->getSelectedDatabase();
        $tables = $this->controller->getTables();

        $html = '<div class="database-overview">';
        $html .= $this->getDropMessages();
        $html .= '<div class="database-header">
                <h2><i class="fas fa-database"></i> ' . htmlspecialchars($selectedDb) . '</h2>
                <div class="database-stats">
                    <span class="stat"><i class="fas fa-table"></i> ' . count($tables) . ' Tables</span>
                </div>
            </div>

            <div class="database-content">
                <!-- Top Row: Tables Overview + SQL Query -->
                <div class="database-top-row">
                    <div class="database-section tables-section">
                        <h3>Tables Overview</h3>
                        <div class="tables-grid">';

        if (!empty($tables)) {
            foreach ($tables as $table) {
                $html .= '<div class="table-card">
                    <div class="table-card-header">
                        <i class="fas fa-table"></i>
                        <a href="?db=' . urlencode($selectedDb) . '&table=' . urlencode($table) . '&action=select" class="table-link">
                            ' . htmlspecialchars($table) . '
                        </a>
                    </div>
                    <div class="table-card-actions">
                        <a href="?db=' . urlencode($selectedDb) . '&table=' . urlencode($table) . '&action=structure" class="action-link">
                            <i class="fas fa-info-circle"></i> Structure
                        </a>
                        <a href="?db=' . urlencode($selectedDb) . '&table=' . urlencode($table) . '&action=select" class="action-link">
                            <i class="fas fa-list"></i> Data
                        </a>
                        <a href="?db=' . urlencode($selectedDb) . '&table=' . urlencode($table) . '&action=insert" class="action-link">
                            <i class="fas fa-plus"></i> Insert
                        </a>
                    </div>
                </div>';
            }
        } else {
            $html .= '<div class="no-tables">No tables found in this database</div>';
        }

        $html .= '</div></div>

                    <div class="database-section sql-section">
                        <h3>Run SQL Query</h3>
                        <form method="POST" action="" class="sql-query-form">
                            <input type="hidden" name="action" value="run_sql">
                            <div class="query-input">
                                <textarea name="sql_query" placeholder="Enter your SQL query here..." rows="8" required></textarea>
                            </div>
                            <div class="query-actions">
                                <button type="submit" class="btn-primary">
                                    <i class="fas fa-play"></i> Execute Query
                                </button>
                                <button type="button" onclick="clearQuery()" class="btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </button>
                            </div>
                        </form>';

        // Display query results if any
        if (isset($_SESSION['sql_result'])) {
            $html .= $this->getSqlResultDisplay();
            unset($_SESSION['sql_result']);
        }

        $html .= '</div>
                </div>

                <!-- Bottom Row: Create Table (Full Width) -->
                <div class="database-section create-table-section">
                    <h3>Create New Table</h3>
                    <div class="create-table-container">
                        <form method="POST" action="" class="create-table-form" id="create-table-form">
                            <input type="hidden" name="action" value="create_table">

                            <div class="table-name-input">
                                <label for="table_name">Table Name:</label>
                                <input type="text" id="table_name" name="table_name" required placeholder="Enter table name">
                            </div>

                            <div class="columns-section">
                                <div class="columns-header">
                                    <h4>Table Columns</h4>
                                    <button type="button" onclick="addColumn()" class="btn-secondary btn-small">
                                        <i class="fas fa-plus"></i> Add Column
                                    </button>
                                </div>

                                <div id="columns-container">
                                    <div class="column-row" data-column="1">
                                        <div class="column-input">
                                            <input type="text" name="columns[1][name]" placeholder="Column name" required>
                                        </div>
                                        <div class="column-type">
                                            <select name="columns[1][type]" required>
                                                <option value="">Select Type</option>
                                                <option value="SERIAL">SERIAL</option>
                                                <option value="BIGSERIAL">BIGSERIAL</option>
                                                <option value="INTEGER">INTEGER</option>
                                                <option value="BIGINT">BIGINT</option>
                                                <option value="SMALLINT">SMALLINT</option>
                                                <option value="VARCHAR(255)">VARCHAR(255)</option>
                                                <option value="TEXT">TEXT</option>
                                                <option value="BOOLEAN">BOOLEAN</option>
                                                <option value="DATE">DATE</option>
                                                <option value="TIME">TIME</option>
                                                <option value="TIMESTAMP">TIMESTAMP</option>
                                                <option value="DECIMAL(10,2)">DECIMAL(10,2)</option>
                                                <option value="REAL">REAL</option>
                                                <option value="DOUBLE PRECISION">DOUBLE PRECISION</option>
                                            </select>
                                        </div>
                                        <div class="column-constraints">
                                            <label><input type="checkbox" name="columns[1][primary]"> Primary Key</label>
                                            <label><input type="checkbox" name="columns[1][not_null]"> Not Null</label>
                                            <label><input type="checkbox" name="columns[1][unique]"> Unique</label>
                                        </div>
                                        <div class="column-actions">
                                            <button type="button" onclick="removeColumn(this)" class="btn-danger btn-small" disabled>
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn-primary">
                                    <i class="fas fa-table"></i> Create Table
                                </button>
                                <button type="reset" class="btn-secondary" onclick="resetCreateTableForm()">
                                    <i class="fas fa-undo"></i> Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div></div>';

        return $html;
    }

    private function getDropMessages()
    {
        $html = '';

        // Create table messages
        if (isset($_SESSION['create_success'])) {
            $html .= '<div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                ' . htmlspecialchars($_SESSION['create_success']) . '
            </div>';
            unset($_SESSION['create_success']);
        }

        if (isset($_SESSION['create_error'])) {
            $html .= '<div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                Create table failed: ' . htmlspecialchars($_SESSION['create_error']) . '
            </div>';
            unset($_SESSION['create_error']);
        }

        // Drop table messages
        if (isset($_SESSION['drop_success'])) {
            $html .= '<div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                ' . htmlspecialchars($_SESSION['drop_success']) . '
            </div>';
            unset($_SESSION['drop_success']);
        }

        if (isset($_SESSION['drop_error'])) {
            $html .= '<div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                Drop table failed: ' . htmlspecialchars($_SESSION['drop_error']) . '
            </div>';
            unset($_SESSION['drop_error']);
        }

        // Schema messages
        if (isset($_SESSION['schema_success'])) {
            $html .= '<div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                ' . htmlspecialchars($_SESSION['schema_success']) . '
            </div>';
            unset($_SESSION['schema_success']);
        }

        if (isset($_SESSION['schema_error'])) {
            $html .= '<div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                Schema operation failed: ' . htmlspecialchars($_SESSION['schema_error']) . '
            </div>';
            unset($_SESSION['schema_error']);
        }

        return $html;
    }

    private function getSqlResultDisplay()
    {
        if (!isset($_SESSION['sql_result'])) {
            return '';
        }

        $result = $_SESSION['sql_result'];
        $html = '<div class="sql-result">';

        if (isset($result['error'])) {
            $html .= '<div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                SQL Error: ' . htmlspecialchars($result['error']) . '
            </div>';
        } elseif (isset($result['rows'])) {
            $html .= '<div class="query-success">
                <h4>Query executed successfully</h4>';

            if (is_array($result['rows']) && !empty($result['rows'])) {
                $html .= '<div class="result-table-container">
                    <table class="result-table">
                        <thead><tr>';

                // Get column names from first row
                $columns = array_keys($result['rows'][0]);
                foreach ($columns as $column) {
                    $html .= '<th>' . htmlspecialchars($column) . '</th>';
                }

                $html .= '</tr></thead><tbody>';

                foreach ($result['rows'] as $row) {
                    $html .= '<tr>';
                    foreach ($row as $value) {
                        $displayValue = is_null($value) ? '<em>NULL</em>' : htmlspecialchars($value);
                        $html .= '<td>' . $displayValue . '</td>';
                    }
                    $html .= '</tr>';
                }

                $html .= '</tbody></table></div>';
            } else {
                $html .= '<p>No results returned from query.</p>';
            }

            if (isset($result['affected_rows'])) {
                $html .= '<p class="affected-rows">' . $result['affected_rows'] . ' rows affected</p>';
            }
        }

        $html .= '</div></div>';
        return $html;
    }

    private function getMainContent()
    {
        $selectedTable = $this->controller->getSelectedTable();
        $selectedAction = $this->controller->getSelectedAction();

        if (!$selectedTable) {
            return $this->getDatabaseOverview();
        }

        $html = '<div class="content-header">
            <h2>' . htmlspecialchars($selectedTable) . '</h2>
            <div class="action-tabs">
                <a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . '&table=' . urlencode($selectedTable) . '&action=structure" class="' . ($selectedAction === 'structure' ? 'active' : '') . '">Structure</a>
                <a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . '&table=' . urlencode($selectedTable) . '&action=select" class="' . ($selectedAction === 'select' ? 'active' : '') . '">Data</a>
                <a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . '&table=' . urlencode($selectedTable) . '&action=insert" class="' . ($selectedAction === 'insert' ? 'active' : '') . '">Insert</a>
                <a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . '&table=' . urlencode($selectedTable) . '&action=delete" class="' . ($selectedAction === 'delete' ? 'active' : '') . '">Delete</a>
            </div>
        </div>';

        $html .= $this->getTableActionContent();

        return $html;
    }

    private function getTableActionContent()
    {
        $action = $this->controller->getSelectedAction();

        switch ($action) {
            case 'structure':
                return $this->getTableStructureHtml();
            case 'select':
                return $this->getTableDataHtml();
            case 'insert':
                return $this->getInsertFormHtml();
            case 'delete':
                return $this->getDeleteFormHtml();
            case 'drop':
                return $this->getDropTableFormHtml();
            default:
                return '<div class="error">Unknown action</div>';
        }
    }

    private function getTableStructureHtml()
    {
        $structure = $this->controller->getTableStructure();
        $subAction = $this->controller->getSelectedSubAction();
        $selectedTable = $this->controller->getSelectedTable();

        if (empty($structure)) {
            return '<div class="error">Unable to load table structure</div>';
        }

        $html = '<div class="structure-container">
            <h3>Table Structure</h3>';

        // Add structure messages if any
        $structureMessage = $this->controller->getStructureMessage();
        if ($structureMessage) {
            $html .= '<div class="alert alert-' . $structureMessage['type'] . '">
                <i class="fas fa-' . ($structureMessage['type'] === 'success' ? 'check-circle' : 'exclamation-triangle') . '"></i>
                ' . htmlspecialchars($structureMessage['text']) . '
            </div>';
        }

        // Show subaction forms if needed
        if ($subAction === 'add_field') {
            $html .= $this->getAddFieldForm();
        } elseif ($subAction === 'edit_field' && isset($_GET['field'])) {
            $fieldName = $_GET['field'];
            // Find the field in structure
            $fieldData = null;
            foreach ($structure as $field) {
                if ($field['name'] === $fieldName) {
                    $fieldData = $field;
                    break;
                }
            }
            if ($fieldData) {
                $html .= $this->getEditFieldForm($fieldData);
            } else {
                $html .= '<div class="error">Field not found</div>';
            }
        } else {
            // Show regular structure table with action buttons
            $html .= '<div class="structure-actions">
                <a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . '&table=' . urlencode($selectedTable) . '&action=structure&subaction=add_field" class="btn-primary">
                    <i class="fas fa-plus"></i> Add New Field
                </a>
            </div>';

            $html .= '<div class="structure-table-wrapper">
                <table class="structure-table">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Type</th>
                            <th>Nullable</th>
                            <th>Default</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>';

            foreach ($structure as $field) {
                $html .= '<tr>
                    <td><strong>' . htmlspecialchars($field['name']) . '</strong></td>
                    <td>' . htmlspecialchars($field['type']) . '</td>
                    <td>' . ($field['nullable'] ? 'YES' : 'NO') . '</td>
                    <td>' . htmlspecialchars($field['default'] ?? 'NULL') . '</td>
                    <td>
                        <a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . '&table=' . urlencode($selectedTable) . '&action=structure&subaction=edit_field&field=' . urlencode($field['name']) . '" class="btn-secondary btn-small">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <form method="POST" action="" style="display: inline;" onsubmit="return confirm(\'Are you sure you want to delete the field &quot;' . htmlspecialchars($field['name']) . '&quot;?\');">
                            <input type="hidden" name="action" value="delete_field">
                            <input type="hidden" name="field_name" value="' . htmlspecialchars($field['name']) . '">
                            <button type="submit" class="btn-danger btn-small" title="Delete Field">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </td>
                </tr>';
            }

            $html .= '</tbody></table></div>';
        }

        $html .= '</div>';
        return $html;
    }

    private function getTableDataHtml()
    {
        $data = $this->controller->getTableData();
        $structure = $this->controller->getTableStructure();
        $selectedSubAction = $this->controller->getSelectedSubAction();

        // Check if we're importing CSV
        if ($selectedSubAction === 'import_csv') {
            return $this->getCsvImportFormHtml();
        }

        // Check if we're exporting CSV
        if ($selectedSubAction === 'export_csv') {
            return $this->handleCsvExport();
        }

        // Check if we're editing a row
        if ($selectedSubAction === 'edit') {
            // Get the row ID from the URL
            $rowId = $_GET['row_id'] ?? null;
            if ($rowId) {
                // Try to find primary key column
                $primaryKeyColumn = null;
                foreach ($structure as $column) {
                    if (isset($column['primary_key']) && $column['primary_key']) {
                        $primaryKeyColumn = $column['name'];
                        break;
                    }
                }

                // Fallback: look for common ID field names
                if (!$primaryKeyColumn) {
                    $idFields = ['id', 'pk', 'primary_key', 'key'];
                    foreach ($structure as $column) {
                        if (in_array(strtolower($column['name']), $idFields)) {
                            $primaryKeyColumn = $column['name'];
                            break;
                        }
                    }
                }

                // Last resort: use first column
                if (!$primaryKeyColumn && !empty($structure)) {
                    $primaryKeyColumn = $structure[0]['name'];
                }

                if ($primaryKeyColumn) {
                    // Load the row data for editing
                    $this->controller->loadEditRowData($rowId, $primaryKeyColumn);
                    $editRowData = $this->controller->getEditRowData();
                    if ($editRowData) {
                        return $this->getEditFormHtml($editRowData, $primaryKeyColumn, $rowId);
                    }
                }
            }
        }

        $html = $this->getInsertMessages();
        $html .= $this->getDeleteMessages();
        $html .= $this->getEditMessages(); // Add edit messages
        $html .= $this->getCsvMessages(); // Add CSV messages

        if (empty($data)) {
            $html .= '<div class="no-data">No data found in this table</div>';
            return $html;
        }

        $html .= '<form method="POST" action="" onsubmit="return confirmDelete()">
            <input type="hidden" name="action" value="delete_rows">
            <div class="data-table-container">
                <div class="table-actions">
                    <button type="button" onclick="toggleAllCheckboxes()" class="btn-secondary">
                        <i class="fas fa-check-square"></i> Select All
                    </button>
                    <button type="submit" class="btn-danger">
                        <i class="fas fa-trash"></i> Delete Selected (<span id="selection-count">0</span>)
                    </button>
                    <a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . '&table=' . urlencode($this->controller->getSelectedTable()) . '&action=select&subaction=import_csv" class="btn-primary">
                        <i class="fas fa-file-import"></i> Import CSV
                    </a>
                    <a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . '&table=' . urlencode($this->controller->getSelectedTable()) . '&action=select&subaction=export_csv" class="btn-secondary">
                        <i class="fas fa-file-export"></i> Export CSV
                    </a>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all" onchange="toggleAllCheckboxes()"></th>';

        foreach ($structure as $field) {
            $html .= '<th>' . htmlspecialchars($field['name']) . '</th>';
        }
        
        // Add Actions column
        $html .= '<th>Actions</th>';

        $html .= '</tr></thead><tbody>';

        foreach ($data as $row) {
            // Get the primary key value for this row
            $primaryKeyValue = null;
            $primaryKeyColumn = null;
            
            // Try to find primary key column
            foreach ($structure as $column) {
                if (isset($column['primary_key']) && $column['primary_key']) {
                    $primaryKeyColumn = $column['name'];
                    $primaryKeyValue = $row[$column['name']] ?? null;
                    break;
                }
            }

            // Fallback: look for common ID field names
            if (!$primaryKeyColumn) {
                $idFields = ['id', 'pk', 'primary_key', 'key'];
                foreach ($structure as $column) {
                    if (in_array(strtolower($column['name']), $idFields)) {
                        $primaryKeyColumn = $column['name'];
                        $primaryKeyValue = $row[$column['name']] ?? null;
                        break;
                    }
                }
            }

            // Last resort: use first column
            if (!$primaryKeyColumn && !empty($structure)) {
                $primaryKeyColumn = $structure[0]['name'];
                $primaryKeyValue = $row[$structure[0]['name']] ?? null;
            }

            $html .= '<tr>
                <td><input type="checkbox" name="selected_rows[]" value="' . htmlspecialchars($primaryKeyValue) . '" onchange="updateSelectionCount()"></td>';

            foreach ($structure as $field) {
                $fieldName = $field['name'];
                $value = $row[$fieldName] ?? '';
                $displayValue = is_null($value) ? '<em>NULL</em>' : htmlspecialchars($value);
                $html .= '<td>' . $displayValue . '</td>';
            }

            // Add Edit button
            $html .= '<td>
                <a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . 
                         '&table=' . urlencode($this->controller->getSelectedTable()) . 
                         '&action=select&subaction=edit&row_id=' . urlencode($primaryKeyValue) . 
                         '" class="btn-secondary btn-small">
                    <i class="fas fa-edit"></i> Edit
                </a>
            </td>';

            $html .= '</tr>';
        }

        $html .= '</tbody></table></div></div></form>';
        return $html;
    }

    // Add method to display edit messages
    private function getEditMessages()
    {
        $html = '';

        if ($this->controller->getEditSuccess()) {
            $html .= '<div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Record updated successfully!
            </div>';
        }

        if ($this->controller->getEditError()) {
            $html .= '<div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                Update failed: ' . htmlspecialchars($this->controller->getEditError()) . '
            </div>';
        }

        return $html;
    }

    // Add method to generate the edit form HTML
    private function getEditFormHtml($rowData, $primaryKeyColumn, $rowId)
    {
        $structure = $this->controller->getTableStructure();
        $selectedDatabase = $this->controller->getSelectedDatabase();
        $selectedTable = $this->controller->getSelectedTable();

        $html = '<div class="insert-form-container">
            <h3>Edit Record</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_row">
                <input type="hidden" name="row_id" value="' . htmlspecialchars($rowId) . '">
                <div class="form-grid">';

        foreach ($structure as $field) {
            $fieldName = $field['name'];
            $fieldType = strtolower($field['type']);
            $fieldValue = $rowData[$fieldName] ?? '';
            
            // Skip the primary key field as it shouldn't be editable
            if ($fieldName === $primaryKeyColumn) {
                $html .= '<input type="hidden" name="' . htmlspecialchars($fieldName) . '" value="' . htmlspecialchars($fieldValue) . '">';
                continue;
            }

            $html .= '<div class="form-field">
                <label class="form-label">
                    <span class="field-name">' . htmlspecialchars($fieldName) . '</span>
                    <span class="field-type">(' . htmlspecialchars($field['type']) . ')</span>
                </label>';

            // NULL checkbox for nullable fields
            if ($field['nullable']) {
                $isChecked = is_null($fieldValue) ? ' checked' : '';
                $html .= '<div class="null-option">
                    <input type="checkbox" id="' . htmlspecialchars($fieldName) . '_null" name="' . htmlspecialchars($fieldName) . '_null" value="1"' . $isChecked . ' onchange="toggleNullField(\'' . htmlspecialchars($fieldName) . '\')">
                    <label for="' . htmlspecialchars($fieldName) . '_null">Set to NULL</label>
                </div>';
            }

            $html .= '<div id="' . htmlspecialchars($fieldName) . '_input_container" class="input-container' . ($field['nullable'] ? '' : ' always-visible') . '">';

            // Render different input types based on field type
            if (strpos($fieldType, 'text') !== false || (strpos($fieldType, 'char') !== false && strlen($fieldType) > 100)) {
                $html .= '<textarea name="' . htmlspecialchars($fieldName) . '" class="form-control" placeholder="Enter ' . htmlspecialchars($fieldName) . '">' . htmlspecialchars($fieldValue) . '</textarea>';
            } elseif ($fieldType === 'boolean') {
                $html .= '<select name="' . htmlspecialchars($fieldName) . '" class="form-control">
                    <option value="">Select...</option>
                    <option value="true"' . ($fieldValue === 't' || $fieldValue === 'true' ? ' selected' : '') . '>TRUE</option>
                    <option value="false"' . ($fieldValue === 'f' || $fieldValue === 'false' ? ' selected' : '') . '>FALSE</option>
                </select>';
            } elseif (strpos($fieldType, 'timestamp') !== false || strpos($fieldType, 'date') !== false) {
                $html .= '<input type="datetime-local" name="' . htmlspecialchars($fieldName) . '" class="form-control" placeholder="Enter ' . htmlspecialchars($fieldName) . '" value="' . htmlspecialchars($fieldValue) . '">';
            } elseif (strpos($fieldType, 'int') !== false || strpos($fieldType, 'float') !== false || strpos($fieldType, 'double') !== false || strpos($fieldType, 'decimal') !== false) {
                $html .= '<input type="number" name="' . htmlspecialchars($fieldName) . '" class="form-control" placeholder="Enter ' . htmlspecialchars($fieldName) . '" value="' . htmlspecialchars($fieldValue) . '">';
            } else {
                $html .= '<input type="text" name="' . htmlspecialchars($fieldName) . '" class="form-control" placeholder="Enter ' . htmlspecialchars($fieldName) . '" value="' . htmlspecialchars($fieldValue) . '">';
            }

            $html .= '</div></div>';
        }

        $html .= '</div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Update Record
                    </button>
                    <a href="?db=' . urlencode($selectedDatabase) . '&table=' . urlencode($selectedTable) . '&action=select" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                </div>
            </form>
        </div>';

        return $html;
    }

    private function getDeleteFormHtml()
    {
        $data = $this->controller->getTableData();
        $structure = $this->controller->getTableStructure();
        $selectedTable = $this->controller->getSelectedTable();

        $html = $this->getDeleteMessages();

        // Show delete all confirmation interface
        $html .= '<div class="delete-all-container">
            <div class="delete-all-warning">
                <h3><i class="fas fa-exclamation-triangle"></i> Delete All Records</h3>
                <div class="warning-content">
                    <p><strong>Warning:</strong> You are about to delete <strong>ALL</strong> records from the table <strong>"' . htmlspecialchars($selectedTable) . '"</strong>.</p>
                    <p>This action will permanently delete <strong>' . count($data) . '</strong> records and cannot be undone.</p>
                    <p>Are you sure you want to proceed?</p>
                </div>

                <div class="delete-all-actions">
                    <a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . '&table=' . urlencode($selectedTable) . '&action=select" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                    <form method="POST" action="" style="display: inline;" onsubmit="return confirmDeleteAll()">
                        <input type="hidden" name="action" value="delete_all_records">
                        <button type="submit" class="btn-danger">
                            <i class="fas fa-trash"></i> Yes, Delete All Records
                        </button>
                    </form>
                </div>
            </div>
        </div>';

        return $html;
    }

    private function getDropTableFormHtml()
    {
        $selectedTable = $this->controller->getSelectedTable();

        $html = '<div class="drop-table-container">
            <h3>Drop Table</h3>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>WARNING:</strong> You are about to permanently delete the table "<strong>' . htmlspecialchars($selectedTable) . '</strong>".
                <br><br>
                This action will:
                <ul>
                    <li>Delete the entire table and all its data</li>
                    <li>Remove all indexes and constraints</li>
                    <li>Cannot be undone</li>
                </ul>
                <br>
                Are you sure you want to proceed?
            </div>

            <form method="POST" action="" onsubmit="return confirmDropTableFinal()">
                <input type="hidden" name="action" value="drop_table">
                <input type="hidden" name="table_name" value="' . htmlspecialchars($selectedTable) . '">

                <div class="form-actions">
                    <button type="submit" class="btn-danger">
                        <i class="fas fa-times-circle"></i> Yes, Drop Table
                    </button>
                    <a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . '&table=' . urlencode($selectedTable) . '&action=select" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                </div>
            </form>
        </div>';

        return $html;
    }

    private function getInsertFormHtml()
    {
        $structure = $this->controller->getTableStructure();

        $html = $this->getInsertMessages();

        if (empty($structure)) {
            return $html . '<div class="error">Unable to load table structure</div>';
        }

        $html .= '<form method="POST" action="">
            <input type="hidden" name="action" value="do_insert">
            <div class="insert-form-container">
                <h3>Insert New Record</h3>
                <div class="form-grid">';

        foreach ($structure as $field) {
            $html .= $this->renderField($field);
        }

        $html .= '</div>
            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Insert Record
                </button>
                <button type="reset" class="btn-secondary" onclick="resetForm()">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form></div>';

        return $html;
    }

    private function renderField($field)
    {
        $name = $field['name'];
        $type = strtolower($field['type']);
        $isSerial = $this->controller->isSerialField($type);
        $isIdField = $this->controller->isIdField($name);

        if ($isIdField || $isSerial) {
            return $this->renderAutoIncrementField($field, $isSerial);
        }

        return $this->renderRegularField($field);
    }

    private function renderAutoIncrementField($field, $isSerial)
    {
        $name = $field['name'];
        $type = $field['type'];

        $html = '<div class="form-field auto-increment-field">
            <label class="form-label">
                <span class="field-name">' . htmlspecialchars($name) . '</span>
                <span class="field-type">(' . htmlspecialchars($type) . ')</span>
            </label>
            <div class="auto-increment-options">
                <div class="option-group">
                    <input type="radio" id="' . htmlspecialchars($name) . '_auto" name="' . htmlspecialchars($name) . '_mode" value="auto" checked onchange="toggleAutoIncrement(\'' . htmlspecialchars($name) . '\', true)">
                    <label for="' . htmlspecialchars($name) . '_auto" class="option-label">
                        <i class="fas fa-magic"></i>
                        <span>Auto-increment</span>
                        <small>' . ($isSerial ? 'SERIAL field - will auto-generate' : 'Automatically generate ID') . '</small>
                    </label>
                </div>
                <div class="option-group">
                    <input type="radio" id="' . htmlspecialchars($name) . '_manual" name="' . htmlspecialchars($name) . '_mode" value="manual" onchange="toggleAutoIncrement(\'' . htmlspecialchars($name) . '\', false)">
                    <label for="' . htmlspecialchars($name) . '_manual" class="option-label">
                        <i class="fas fa-edit"></i>
                        <span>Manual input</span>
                        <small>Enter ID value manually</small>
                    </label>
                </div>
            </div>
            <div id="' . htmlspecialchars($name) . '_container" class="manual-input-container" style="display: none;">
                <input type="number" id="' . htmlspecialchars($name) . '_manual_input" name="' . htmlspecialchars($name) . '" class="form-control" placeholder="Enter ' . htmlspecialchars($name) . ' value" min="1">
            </div>
        </div>';

        return $html;
    }

    private function renderRegularField($field)
    {
        $name = $field['name'];
        $type = strtolower($field['type']);
        $nullable = $field['nullable'];

        $html = '<div class="form-field">
            <label class="form-label">
                <span class="field-name">' . htmlspecialchars($name) . '</span>
                <span class="field-type">(' . htmlspecialchars($field['type']) . ')</span>
            </label>';

        // NULL checkbox for nullable fields
        if ($nullable) {
            $html .= '<div class="null-option">
                <input type="checkbox" id="' . htmlspecialchars($name) . '_null" name="' . htmlspecialchars($name) . '_null" value="1" onchange="toggleNullField(\'' . htmlspecialchars($name) . '\')">
                <label for="' . htmlspecialchars($name) . '_null">Set to NULL</label>
            </div>';
        }

        $html .= '<div id="' . htmlspecialchars($name) . '_input_container" class="input-container' . ($nullable ? '' : ' always-visible') . '">';

        // Render different input types based on field type
        $inputType = $this->getInputType($type);
        $placeholder = 'Enter ' . htmlspecialchars($name);

        if ($inputType === 'textarea') {
            $html .= '<textarea name="' . htmlspecialchars($name) . '" class="form-control" placeholder="' . $placeholder . '"></textarea>';
        } elseif ($type === 'boolean') {
            $html .= '<select name="' . htmlspecialchars($name) . '" class="form-control">
                <option value="">Select...</option>
                <option value="true">TRUE</option>
                <option value="false">FALSE</option>
            </select>';
        } elseif (strpos($type, 'timestamp') !== false || strpos($type, 'date') !== false) {
            $html .= '<input type="datetime-local" name="' . htmlspecialchars($name) . '" class="form-control" placeholder="' . $placeholder . '">';
        } else {
            $html .= '<input type="' . $inputType . '" name="' . htmlspecialchars($name) . '" class="form-control" placeholder="' . $placeholder . '">';
        }

        $html .= '</div></div>';
        return $html;
    }

    private function getInputType($type)
    {
        if (strpos($type, 'int') !== false) return 'number';
        if (strpos($type, 'float') !== false || strpos($type, 'double') !== false || strpos($type, 'decimal') !== false) return 'number';
        if (strpos($type, 'text') !== false || strpos($type, 'varchar') !== false) return 'text';
        if ($type === 'boolean') return 'select';
        if (strpos($type, 'timestamp') !== false || strpos($type, 'date') !== false) return 'datetime-local';
        if (strpos($type, 'char') !== false && strlen($type) > 100) return 'textarea';
        return 'text';
    }

    private function getInsertMessages()
    {
        $html = '';

        if ($this->controller->getInsertSuccess()) {
            $html .= '<div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Record inserted successfully!
            </div>';
        }

        if ($this->controller->getInsertError()) {
            $html .= '<div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                Insert failed: ' . htmlspecialchars($this->controller->getInsertError()) . '
            </div>';
        }

        return $html;
    }

    private function getDeleteMessages()
    {
        $html = '';

        if ($this->controller->getDeleteSuccessCount() > 0) {
            $html .= '<div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Successfully deleted ' . $this->controller->getDeleteSuccessCount() . ' record(s)!
            </div>';
        }

        if ($this->controller->getDeleteError()) {
            $html .= '<div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                Delete failed: ' . htmlspecialchars($this->controller->getDeleteError()) . '
            </div>';
        }

        return $html;
    }

    /**
     * Get the HTML form for adding a new field
     */
    private function getAddFieldForm()
    {
        $selectedDatabase = $this->controller->getSelectedDatabase();
        $selectedTable = $this->controller->getSelectedTable();

        $html = '<div class="add-field-form">
            <h3>Add New Field</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_field">
                <div class="form-grid">
                    <div class="form-field">
                        <label class="form-label" for="field_name">Field Name</label>
                        <input type="text" id="field_name" name="field_name" class="form-control" required placeholder="Enter field name">
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="field_type">Field Type</label>
                        <select id="field_type" name="field_type" class="form-control" required>
                            <option value="">Select Type</option>
                            <option value="SERIAL">SERIAL</option>
                            <option value="BIGSERIAL">BIGSERIAL</option>
                            <option value="INTEGER">INTEGER</option>
                            <option value="BIGINT">BIGINT</option>
                            <option value="SMALLINT">SMALLINT</option>
                            <option value="VARCHAR(255)">VARCHAR(255)</option>
                            <option value="TEXT">TEXT</option>
                            <option value="BOOLEAN">BOOLEAN</option>
                            <option value="DATE">DATE</option>
                            <option value="TIME">TIME</option>
                            <option value="TIMESTAMP">TIMESTAMP</option>
                            <option value="DECIMAL(10,2)">DECIMAL(10,2)</option>
                            <option value="REAL">REAL</option>
                            <option value="DOUBLE PRECISION">DOUBLE PRECISION</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label class="form-label">
                            <input type="checkbox" name="is_nullable" value="1"> Nullable
                        </label>
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="default_value">Default Value (optional)</label>
                        <input type="text" id="default_value" name="default_value" class="form-control" placeholder="Enter default value">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-plus"></i> Add Field
                    </button>
                    <a href="?db=' . urlencode($selectedDatabase) . '&table=' . urlencode($selectedTable) . '&action=structure" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                </div>
            </form>
        </div>';

        return $html;
    }

    /**
     * Get the HTML form for editing an existing field
     */
    private function getEditFieldForm($fieldData)
    {
        $selectedDatabase = $this->controller->getSelectedDatabase();
        $selectedTable = $this->controller->getSelectedTable();
        $fieldName = $fieldData['name'];
        $fieldType = $fieldData['type'];
        $isNullable = $fieldData['nullable'];
        $defaultValue = $fieldData['default'] ?? '';

        $html = '<div class="edit-field-form">
            <h3>Edit Field: ' . htmlspecialchars($fieldName) . '</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_field">
                <input type="hidden" name="original_field_name" value="' . htmlspecialchars($fieldName) . '">
                <div class="form-grid">
                    <div class="form-field">
                        <label class="form-label" for="field_name">Field Name</label>
                        <input type="text" id="field_name" name="field_name" class="form-control" required placeholder="Enter field name" value="' . htmlspecialchars($fieldName) . '">
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="field_type">Field Type</label>
                        <select id="field_type" name="field_type" class="form-control" required>
                            <option value="">Select Type</option>
                            <option value="SERIAL"' . ($fieldType === 'serial' ? ' selected' : '') . '>SERIAL</option>
                            <option value="BIGSERIAL"' . ($fieldType === 'bigserial' ? ' selected' : '') . '>BIGSERIAL</option>
                            <option value="INTEGER"' . ($fieldType === 'integer' ? ' selected' : '') . '>INTEGER</option>
                            <option value="BIGINT"' . ($fieldType === 'bigint' ? ' selected' : '') . '>BIGINT</option>
                            <option value="SMALLINT"' . ($fieldType === 'smallint' ? ' selected' : '') . '>SMALLINT</option>
                            <option value="VARCHAR(255)"' . (strpos($fieldType, 'character varying') !== false ? ' selected' : '') . '>VARCHAR(255)</option>
                            <option value="TEXT"' . ($fieldType === 'text' ? ' selected' : '') . '>TEXT</option>
                            <option value="BOOLEAN"' . ($fieldType === 'boolean' ? ' selected' : '') . '>BOOLEAN</option>
                            <option value="DATE"' . ($fieldType === 'date' ? ' selected' : '') . '>DATE</option>
                            <option value="TIME"' . ($fieldType === 'time without time zone' ? ' selected' : '') . '>TIME</option>
                            <option value="TIMESTAMP"' . ($fieldType === 'timestamp without time zone' ? ' selected' : '') . '>TIMESTAMP</option>
                            <option value="DECIMAL(10,2)"' . (strpos($fieldType, 'numeric') !== false ? ' selected' : '') . '>DECIMAL(10,2)</option>
                            <option value="REAL"' . ($fieldType === 'real' ? ' selected' : '') . '>REAL</option>
                            <option value="DOUBLE PRECISION"' . ($fieldType === 'double precision' ? ' selected' : '') . '>DOUBLE PRECISION</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label class="form-label">
                            <input type="checkbox" name="is_nullable" value="1"' . ($isNullable ? ' checked' : '') . '> Nullable
                        </label>
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="default_value">Default Value (optional)</label>
                        <input type="text" id="default_value" name="default_value" class="form-control" placeholder="Enter default value" value="' . htmlspecialchars($defaultValue) . '">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="?db=' . urlencode($selectedDatabase) . '&table=' . urlencode($selectedTable) . '&action=structure" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                </div>
            </form>
        </div>';

        return $html;
    }

    // Add method to display CSV messages
    private function getCsvMessages()
    {
        $html = '';

        if ($this->controller->getCsvImportSuccess()) {
            $html .= '<div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                CSV file imported successfully!
            </div>';
        }

        if ($this->controller->getCsvImportError()) {
            $html .= '<div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                CSV import failed: ' . htmlspecialchars($this->controller->getCsvImportError()) . '
            </div>';
        }

        if ($this->controller->getCsvExportSuccess()) {
            $html .= '<div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                CSV file exported successfully!
            </div>';
        }

        if ($this->controller->getCsvExportError()) {
            $html .= '<div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                CSV export failed: ' . htmlspecialchars($this->controller->getCsvExportError()) . '
            </div>';
        }

        return $html;
    }

    // Add method to generate the CSV import form HTML
    private function getCsvImportFormHtml()
    {
        $selectedDatabase = $this->controller->getSelectedDatabase();
        $selectedTable = $this->controller->getSelectedTable();

        $html = '<div class="insert-form-container">
            <h3>Import CSV Data</h3>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_csv">
                <div class="form-grid">
                    <div class="form-field">
                        <label class="form-label" for="csv_file">CSV File</label>
                        <input type="file" id="csv_file" name="csv_file" class="form-control" accept=".csv,text/csv" required>
                        <small class="form-text">Please ensure your CSV file has headers that match the table columns.</small>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-file-import"></i> Import CSV
                    </button>
                    <a href="?db=' . urlencode($selectedDatabase) . '&table=' . urlencode($selectedTable) . '&action=select" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                </div>
            </form>
            
            <div class="info-box">
                <h4>CSV Import Guidelines:</h4>
                <ul>
                    <li>The first row of your CSV file should contain column headers</li>
                    <li>Headers must match the table column names exactly</li>
                    <li>Use UTF-8 encoding for best compatibility</li>
                    <li>Date/time values should be in ISO format (YYYY-MM-DD HH:MM:SS)</li>
                    <li>Boolean values should be represented as TRUE/FALSE or 1/0</li>
                    <li>NULL values should be empty cells in the CSV</li>
                </ul>
            </div>
        </div>';

        return $html;
    }

    // Add method to handle CSV export
    private function handleCsvExport()
    {
        // Redirect to trigger export
        $selectedDatabase = $this->controller->getSelectedDatabase();
        $selectedTable = $this->controller->getSelectedTable();
        
        // Create a form to trigger the export
        $html = '<div class="insert-form-container">
            <h3>Export CSV Data</h3>
            <p>Click the button below to export all data from the "' . htmlspecialchars($selectedTable) . '" table to a CSV file.</p>
            <form method="POST" action="">
                <input type="hidden" name="action" value="export_csv">
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-file-export"></i> Export to CSV
                    </button>
                    <a href="?db=' . urlencode($selectedDatabase) . '&table=' . urlencode($selectedTable) . '&action=select" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                </div>
            </form>
        </div>';

        return $html;
    }

    private function getHtmlScripts()
    {
        return '<script>
            ' . $this->getJavaScript() . '
        </script>';
    }

    private function getCss()
    {
        return '
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: \'Inter\', sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 300px;
            background: #2c3e50;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header h3 {
            padding: 0 20px;
            margin-bottom: 20px;
            font-size: 1.2em;
            color: #ecf0f1;
        }

        .database-item {
            margin-bottom: 5px;
        }

        .database-item a {
            display: block;
            padding: 12px 20px;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s;
            border-radius: 8px;
        }

        .database-item a:hover {
            background: #34495e;
            color: white;
        }

        .database-item.active a {
            background: #3498db;
            color: white;
            font-weight: 600;
        }

        .tables-list {
            list-style: none;
            margin-top: 10px;
            margin-left: 20px;
        }

        .table-item {
            margin-bottom: 5px;
        }

        .table-name {
            display: block;
            padding: 8px 15px;
            font-size: 0.9em;
            color: #95a5a6;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .table-name:hover {
            color: #bdc3c7;
        }

        .table-item.active .table-name {
            color: #ecf0f1;
            font-weight: 600;
        }

        .table-item.active .table-name:hover {
            color: #ecf0f1;
        }

        .table-actions {
            list-style: none;
            margin-left: 2px;
            margin-top: 1px;
        }

        .table-actions a {
            display: block;
            padding: 6px 15px;
            color:rgb(68, 69, 99)!important;
            background: #f8f9fa !important;
            text-decoration: none;
            font-size: 0.85em;
            transition: all 0.3s;
        }

        .table-actions a:hover {
            color:rgb(255, 255, 255);
            background: rgba(255,255,255,0.1);
        }

        .main-content {
            flex: 1;
            margin-left: 300px;
        }

        .dashboard-header {
            background: white;
            padding: 20px 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dashboard-header h1 {
            color: #2c3e50;
            font-size: 1.8em;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            color: #7f8c8d;
            font-size: 0.9em;
        }

        .logout-btn {
            background: #e74c3c;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background: #c0392b;
        }

        .dashboard-main {
            padding: 30px;
        }

        .welcome-message {
            text-align: center;
            padding: 60px 20px;
        }

        .welcome-message h2 {
            color: #2c3e50;
            margin-bottom: 20px;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #ecf0f1;
        }

        .content-header h2 {
            color: #2c3e50;
            font-size: 1.5em;
        }

        .action-tabs {
            display: flex;
            gap: 5px;
            background: #f8f9fa;
            border-radius: 6px;
            padding: 4px;
        }

        .action-tabs a {
            padding: 10px 20px;
            text-decoration: none;
            color: #7f8c8d;
            border-radius: 4px;
            transition: all 0.3s;
            font-weight: 500;
        }

        .action-tabs a:hover {
            background: #e9ecef;
            color: #495057;
        }

        .action-tabs a.active {
            background: #3498db;
            color: white;
        }

        .structure-container h3,
        .insert-form-container h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.3em;
        }

        .structure-table-wrapper {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .structure-table {
            width: 100%;
            border-collapse: collapse;
        }

        .structure-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #dee2e6;
        }

        .structure-table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }

        .data-table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table-actions {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .info-box {
            background: #e8f4f8;
            border: 1px solid #bee0ec;
            border-radius: 6px;
            padding: 20px;
            margin-top: 20px;
        }

        .info-box h4 {
            color: #2c3e50;
            margin-top: 0;
            margin-bottom: 15px;
        }

        .info-box ul {
            margin: 0;
            padding-left: 20px;
        }

        .info-box li {
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .form-text {
            display: block;
            margin-top: 5px;
            color: #6c757d;
            font-size: 0.875em;
        }

        /* Delete All Records Styles */
        .delete-all-container {
            max-width: 700px;
            margin: 20px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid #e1e8ed;
        }

        .delete-all-warning {
            padding: 30px;
            text-align: center;
        }

        .delete-all-warning h3 {
            color: #e74c3c;
            margin: 0 0 20px 0;
            font-size: 24px;
            font-weight: 600;
        }

        .delete-all-warning h3 i {
            margin-right: 10px;
        }

        .warning-content {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }

        .warning-content p {
            margin: 10px 0;
            color: #2d3748;
            line-height: 1.6;
        }

        .warning-content p strong {
            color: #e53e3e;
            font-weight: 600;
        }

        .delete-all-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .delete-all-actions .btn-secondary,
        .delete-all-actions .btn-danger {
            min-width: 180px;
            font-weight: 500;
            min-height: 50px;
        }
        
        

        .delete-all-actions form {
            display: inline-block;
        }

        /* Responsive design for mobile */
        @media (max-width: 768px) {
            .delete-all-container {
                margin: 10px;
                max-width: none;
            }

            .delete-all-warning {
                padding: 20px;
            }

            .delete-all-warning h3 {
                font-size: 20px;
            }

            .delete-all-actions {
                flex-direction: column;
                align-items: center;
            }

            .delete-all-actions .btn-secondary,
            .delete-all-actions .btn-danger {
                width: 100%;
                max-width: 250px;
            }
        }

        /* Schema Management Styles */
        .schemas-list {
            list-style: none;
            padding: 0;
            margin: 10px 0;
        }

        .schema-item {
            margin: 5px 0;
            background-color:rgb(14, 37, 60);
        }

        .schema-item > .schema-name {
            display: block;
            padding: 8px 12px;
            color: #666;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.3s;
            font-weight: 500;
        }

        .schema-item > .schema-name:hover {
            background: #f8f9fa;
            color: #333;
        }

        .schema-item.active > .schema-name {
            background:rgb(15, 71, 56);
            color: white;
        }

        .schema-actions {
            display: flex;
            gap: 5px;
            padding: 5px 12px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .schema-item:hover .schema-actions {
            opacity: 1;
        }

        .schema-item.active .schema-actions {
            opacity: 1;
        }

        .schema-actions li {
            list-style: none;
        }

        .schema-actions a {
            color: #666;
            text-decoration: none;
            padding: 5px;
            border-radius: 3px;
            transition: all 0.3s;
        }

        .schema-actions a:hover {
            background: #f0f0f0;
            color: #333;
        }

        .schema-actions a[href*="rename"] {
            color: #f39c12;
        }

        .schema-actions a[href*="rename"]:hover {
            background: #f39c12;
            color: white;
        }

        .schema-actions a[href*="drop"] {
            color: #e74c3c;
        }

        .schema-actions a[href*="drop"]:hover {
            background: #e74c3c;
            color: white;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: modalFadeIn 0.3s ease-out;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-50px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            padding: 20px 30px;
            border-bottom: 1px solid #e1e8ed;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: #2d3748;
            font-size: 20px;
        }

        .modal-close {
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
            transition: color 0.3s;
        }

        .modal-close:hover {
            color: #333;
        }

        .modal-body {
            padding: 30px;
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #e1e8ed;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2d3748;
        }

        .form-group input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input[type="text"]:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .form-group input[readonly] {
            background: #f8f9fa;
            color: #6c757d;
        }

        .form-hint {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: #6c757d;
        }

        .warning-message {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .warning-message p {
            margin: 0 0 10px 0;
            color: #2d3748;
        }

        .warning-message p:last-child {
            margin-bottom: 0;
        }

        /* Responsive modal styles */
        @media (max-width: 768px) {
            .modal-content {
                margin: 5% auto;
                width: 95%;
            }

            .modal-header,
            .modal-body,
            .modal-footer {
                padding: 20px;
            }

            .modal-footer {
                flex-direction: column;
            }

            .modal-footer .btn-primary,
            .modal-footer .btn-secondary,
            .modal-footer .btn-danger {
                width: 100%;
                margin-bottom: 10px;
            }
        }

        /* Drop Table Styles */
        .drop-table-container {
            max-width: 600px;
            margin: 0 auto;
        }

        .drop-table-container h3 {
            color: #e74c3c;
            margin-bottom: 20px;
        }

        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .alert-danger strong {
            color: #dc3545;
        }

        .alert-danger ul {
            margin: 10px 0 0 20px;
            padding: 0;
        }

        .alert-danger li {
            margin-bottom: 5px;
        }

        .alert-danger i {
            margin-right: 10px;
            color: #dc3545;
        }

        /* Create Table Styles */
        .create-table-container {
            max-width: 100%;
        }

        .create-table-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .table-name-input {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .table-name-input label {
            font-weight: 600;
            min-width: 100px;
        }

        .table-name-input input {
            flex: 1;
            padding: 10px;
            border: 2px solid #ecf0f1;
            border-radius: 6px;
            font-size: 0.9em;
            transition: border-color 0.2s;
        }

        .table-name-input input:focus {
            outline: none;
            border-color: #3498db;
        }

        .columns-section {
            border: 1px solid #ecf0f1;
            border-radius: 8px;
            padding: 20px;
            background: #f8f9fa;
        }

        .columns-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .columns-header h4 {
            margin: 0;
            color: #2c3e50;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 0.8em;
        }

        #columns-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .column-row {
            display: grid;
            grid-template-columns: 2fr 1.5fr 2fr 80px;
            gap: 10px;
            align-items: center;
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }

        .column-input input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .column-type select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.9em;
            background: white;
        }

        .column-constraints {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .column-constraints label {
            display: flex;
            align-items: center;
            gap: 3px;
            font-size: 0.8em;
            white-space: nowrap;
        }

        .column-constraints input[type="checkbox"] {
            margin: 0;
        }

        .column-actions {
            display: flex;
            justify-content: center;
        }

        .column-actions button {
            padding: 6px 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .column-actions button:hover:not(:disabled) {
            opacity: 0.8;
        }

        .column-actions button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Mobile responsiveness for create table */
        @media (max-width: 768px) {
            .column-row {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .column-constraints {
                justify-content: center;
            }

            .table-name-input {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .columns-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #dee2e6;
            white-space: nowrap;
        }

        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            white-space: nowrap;
        }

        .data-table tr:hover {
            background: #f8f9fa;
        }

        .insert-form-container,
        .delete-form-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 30px;
        }

        .warning-text {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .warning-text i {
            margin-right: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }

        .field-name {
            font-weight: 600;
        }

        .field-type {
            font-size: 0.85em;
            color: #7f8c8d;
            font-weight: normal;
        }

        .form-control {
            padding: 10px 12px;
            border: 2px solid #e9ecef;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-start;
        }

        .auto-increment-field {
            border: 2px solid #e9ecef;
            border-radius: 6px;
            padding: 15px;
            background: #f8f9fa;
        }

        .auto-increment-options {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
        }

        .option-group {
            flex: 1;
        }

        .option-label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .option-group input[type="radio"] {
            display: none;
        }

        .option-group input[type="radio"]:checked + .option-label {
            border-color: #3498db;
            background: #ebf5fb;
        }

        .option-label i {
            color: #3498db;
        }

        .option-label span {
            font-weight: 500;
        }

        .option-label small {
            display: block;
            color: #7f8c8d;
            font-size: 0.8em;
            margin-top: 2px;
        }

        .manual-input-container {
            margin-top: 10px;
        }

        .null-option {
            margin-bottom: 8px;
        }

        .null-option input[type="checkbox"] {
            margin-right: 8px;
        }

        .input-container {
            display: none;
        }

        .input-container.always-visible {
            display: block;
        }

        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
            font-style: italic;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 4px;
            border: 1px solid #f5c6cb;
            text-align: center;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 250px;
            }

            .main-content {
                margin-left: 250px;
            }

            .dashboard-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .content-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .auto-increment-options {
                flex-direction: column;
            }

            .table-actions {
                flex-direction: column;
                align-items: stretch;
            }
        }

        /* Database Overview Styles */
        .database-overview {
            padding: 20px;
        }

        /* Structure Actions */
        .structure-actions {
            margin-bottom: 20px;
        }

        .structure-actions .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        /* Add/Edit Field Forms */
        .add-field-form,
        .edit-field-form {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }

        .add-field-form h3,
        .edit-field-form h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.3em;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 0.85em;
        }

        .btn-small i {
            font-size: 0.9em;
        }

        .database-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ecf0f1;
        }

        .database-header h2 {
            margin: 0;
            color: #2c3e50;
        }

        .database-header h2 i {
            margin-right: 10px;
            color: #3498db;
        }

        .database-stats {
            display: flex;
            gap: 15px;
        }

        .database-stats .stat {
            background: #ecf0f1;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            color: #7f8c8d;
        }

        .database-stats .stat i {
            margin-right: 5px;
        }

        .database-content {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .database-top-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .database-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 25px;
        }

        .database-section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.2em;
        }

        /* Tables Grid */
        .tables-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }

        .table-card {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .table-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .table-card-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .table-card-header i {
            margin-right: 8px;
            color: #3498db;
        }

        .table-link {
            color: #2c3e50;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .table-link:hover {
            color: #3498db;
        }

        .table-card-actions {
            display: flex;
            gap: 10px;
        }

        .action-link {
            color: #7f8c8d;
            text-decoration: none;
            font-size: 0.8em;
            transition: color 0.2s;
        }

        .action-link:hover {
            color: #3498db;
        }

        .action-link i {
            margin-right: 3px;
        }

        .no-tables {
            text-align: center;
            color: #95a5a6;
            padding: 40px;
            font-style: italic;
        }

        /* SQL Query Form */
        .sql-query-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .query-input textarea {
            width: 100%;
            border: 2px solid #ecf0f1;
            border-radius: 6px;
            padding: 15px;
            font-family: "Courier New", monospace;
            font-size: 0.9em;
            resize: vertical;
            transition: border-color 0.2s;
        }

        .query-input textarea:focus {
            outline: none;
            border-color: #3498db;
        }

        .query-actions {
            display: flex;
            gap: 10px;
        }

        .query-actions button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.2s;
        }

        .query-actions .btn-primary {
            background: #3498db;
            color: white;
        }

        .query-actions .btn-primary:hover {
            background: #2980b9;
        }

        .query-actions .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .query-actions .btn-secondary:hover {
            background: #7f8c8d;
        }

        /* SQL Results */
        .sql-result {
            margin-top: 20px;
        }

        .query-success h4 {
            color: #27ae60;
            margin-bottom: 15px;
        }

        .result-table-container {
            overflow-x: auto;
            margin-top: 15px;
        }

        .result-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 4px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .result-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #ecf0f1;
        }

        .result-table td {
            padding: 12px;
            border-bottom: 1px solid #ecf0f1;
        }

        .result-table tr:hover {
            background: #f8f9fa;
        }

        .affected-rows {
            margin-top: 10px;
            color: #27ae60;
            font-weight: 500;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .database-top-row {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .tables-grid {
                grid-template-columns: 1fr;
            }

            .query-actions {
                flex-direction: column;
            }
        }
        ';
    }

    private function getJavaScript()
    {
        return '
        function toggleAutoIncrement(fieldId, isAuto) {
            const container = document.getElementById(fieldId + \'_container\');
            if (container) {
                container.style.display = isAuto ? \'none\' : \'block\';
            }
        }

        function toggleNullField(fieldName) {
            const checkbox = document.getElementById(fieldName + \'_null\');
            const container = document.getElementById(fieldName + \'_input_container\');

            if (checkbox.checked) {
                container.style.display = \'none\';
            } else {
                container.style.display = \'block\';
            }
        }

        function toggleAllCheckboxes() {
            const selectAll = document.getElementById(\'select-all\');
            const checkboxes = document.querySelectorAll(\'input[name="selected_rows[]"]\');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });

            updateSelectionCount();
        }

        function updateSelectionCount() {
            const checkboxes = document.querySelectorAll(\'input[name="selected_rows[]"]:checked\');
            const count = document.getElementById(\'selection-count\');
            if (count) {
                count.textContent = checkboxes.length;
            }
        }

        function confirmDelete() {
            const checkboxes = document.querySelectorAll(\'input[name="selected_rows[]"]:checked\');
            if (checkboxes.length === 0) {
                alert(\'Please select at least one row to delete.\');
                return false;
            }

            return confirm(`Are you sure you want to delete ${checkboxes.length} selected row(s)? This action cannot be undone.`);
        }

        function confirmDeleteAll() {
            return confirm(\'FINAL WARNING: You are about to delete ALL records from this table.\\n\\nThis action cannot be undone!\\n\\nClick OK to proceed or Cancel to stop.\');
        }

        function clearQuery() {
            const textarea = document.querySelector(\'textarea[name="sql_query"]\');
            if (textarea) {
                textarea.value = \'\';
                textarea.focus();
            }
        }

        function confirmDropTable(tableName) {
            return confirm(`Are you sure you want to drop the table "${tableName}"?

This action will permanently delete the table and all its data.

This cannot be undone!`);
        }

        function confirmDropTableFinal() {
            const finalConfirm = prompt(\'To confirm deletion, please type "DROP" in all caps:\');
            if (finalConfirm !== \'DROP\') {
                alert(\'Table drop cancelled. You must type "DROP" to confirm.\');
                return false;
            }
            return confirm(\'FINAL WARNING: This will permanently delete the table and ALL its data!\\n\\nClick OK to proceed or Cancel to stop.\');
        }

        // Schema Modal Functions
        function showCreateSchemaModal() {
            var modal = document.getElementById(\'createSchemaModal\');
            if (modal) {
                modal.style.display = \'block\';
                var input = document.getElementById(\'schema_name\');
                if (input) input.focus();
            }
        }

        function showRenameSchemaModal(schemaName) {
            var oldInput = document.getElementById(\'old_schema_name\');
            var currentDisplay = document.getElementById(\'current_schema_display\');
            var newInput = document.getElementById(\'new_schema_name\');
            var modal = document.getElementById(\'renameSchemaModal\');

            if (oldInput) oldInput.value = schemaName;
            if (currentDisplay) currentDisplay.value = schemaName;
            if (newInput) newInput.value = \'\';
            if (modal) {
                modal.style.display = \'block\';
                if (newInput) newInput.focus();
            }
        }

        function showDropSchemaModal(schemaName) {
            var nameInput = document.getElementById(\'drop_schema_name\');
            var displaySpan = document.getElementById(\'drop_schema_display\');
            var cascadeCheckbox = document.getElementById(\'cascade_option\');
            var modal = document.getElementById(\'dropSchemaModal\');

            if (nameInput) nameInput.value = schemaName;
            if (displaySpan) displaySpan.textContent = schemaName;
            if (cascadeCheckbox) cascadeCheckbox.checked = false;
            if (modal) modal.style.display = \'block\';
        }

        function closeSchemaModal() {
            var createModal = document.getElementById(\'createSchemaModal\');
            var renameModal = document.getElementById(\'renameSchemaModal\');
            var dropModal = document.getElementById(\'dropSchemaModal\');

            if (createModal) createModal.style.display = \'none\';
            if (renameModal) renameModal.style.display = \'none\';
            if (dropModal) dropModal.style.display = \'none\';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            var modals = [\'createSchemaModal\', \'renameSchemaModal\', \'dropSchemaModal\'];
            for (var i = 0; i < modals.length; i++) {
                var modal = document.getElementById(modals[i]);
                if (event.target === modal) {
                    modal.style.display = \'none\';
                }
            }
        };

        let columnCounter = 1;

        function addColumn() {
            columnCounter++;
            const container = document.getElementById(\'columns-container\');
            const columnRow = document.createElement(\'div\');
            columnRow.className = \'column-row\';
            columnRow.setAttribute(\'data-column\', columnCounter);

            columnRow.innerHTML = `
                <div class="column-input">
                    <input type="text" name="columns[${columnCounter}][name]" placeholder="Column name" required>
                </div>
                <div class="column-type">
                    <select name="columns[${columnCounter}][type]" required>
                        <option value="">Select Type</option>
                        <option value="SERIAL">SERIAL</option>
                        <option value="BIGSERIAL">BIGSERIAL</option>
                        <option value="INTEGER">INTEGER</option>
                        <option value="BIGINT">BIGINT</option>
                        <option value="SMALLINT">SMALLINT</option>
                        <option value="VARCHAR(255)">VARCHAR(255)</option>
                        <option value="TEXT">TEXT</option>
                        <option value="BOOLEAN">BOOLEAN</option>
                        <option value="DATE">DATE</option>
                        <option value="TIME">TIME</option>
                        <option value="TIMESTAMP">TIMESTAMP</option>
                        <option value="DECIMAL(10,2)">DECIMAL(10,2)</option>
                        <option value="REAL">REAL</option>
                        <option value="DOUBLE PRECISION">DOUBLE PRECISION</option>
                    </select>
                </div>
                <div class="column-constraints">
                    <label><input type="checkbox" name="columns[${columnCounter}][primary]"> Primary Key</label>
                    <label><input type="checkbox" name="columns[${columnCounter}][not_null]"> Not Null</label>
                    <label><input type="checkbox" name="columns[${columnCounter}][unique]"> Unique</label>
                </div>
                <div class="column-actions">
                    <button type="button" onclick="removeColumn(this)" class="btn-danger btn-small">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;

            container.appendChild(columnRow);
            updateRemoveButtons();
        }

        function removeColumn(button) {
            const columnRow = button.closest(\'.column-row\');
            if (columnRow) {
                columnRow.remove();
                updateRemoveButtons();
            }
        }

        function updateRemoveButtons() {
            const columnRows = document.querySelectorAll(\'.column-row\');
            const removeButtons = document.querySelectorAll(\'.column-actions button\');

            // Enable all remove buttons if there are more than 1 column
            removeButtons.forEach(button => {
                button.disabled = columnRows.length <= 1;
            });
        }

        function resetCreateTableForm() {
            // Reset form
            const form = document.getElementById(\'create-table-form\');
            if (form) {
                form.reset();
            }

            // Reset column counter
            columnCounter = 1;

            // Remove all additional columns except the first one
            const container = document.getElementById(\'columns-container\');
            const columnRows = container.querySelectorAll(\'.column-row\');

            // Keep only the first column
            for (let i = 1; i < columnRows.length; i++) {
                columnRows[i].remove();
            }

            // Reset the first column data attribute
            if (columnRows.length > 0) {
                columnRows[0].setAttribute(\'data-column\', \'1\');
            }

            updateRemoveButtons();
        }

        function resetForm() {
            // Reset all radio buttons to auto-increment
            const radios = document.querySelectorAll(\'input[type="radio"][value="auto"]\');
            radios.forEach(radio => {
                radio.checked = true;
                const fieldId = radio.name.replace(\'_mode\', \'\');
                toggleAutoIncrement(fieldId, true);
            });

            // Reset NULL checkboxes
            const nullCheckboxes = document.querySelectorAll(\'input[type="checkbox"][id$="_null"]\');
            nullCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
                const fieldName = checkbox.id.replace(\'_null\', \'\');
                toggleNullField(fieldName);
            });
        }

        // Initialize on page load
        document.addEventListener(\'DOMContentLoaded\', function() {
            updateSelectionCount();
            updateRemoveButtons();
        });
        ';
    }
}

class Main {
    private $controller;
    private $view;

    public function __construct($connectionData = null) {
        $this->controller = new MainController($connectionData);
        $this->view = new MainView($this->controller);
    }

    public function getHtml() {
        return $this->view->getHtml();
    }

    // Delegate methods to controller for backward compatibility
    public function processInsertForm() {
        return $this->controller->processInsertForm();
    }

    public function processEditForm() {
        return $this->controller->processEditForm();
    }

    public function processDeleteRows() {
        return $this->controller->processDeleteRows();
    }

    public function processDropTable() {
        return $this->controller->processDropTable();
    }

    public function processCreateTable() {
        return $this->controller->processCreateTable();
    }

    public function processSqlQuery() {
        return $this->controller->processSqlQuery();
    }

    public function processDeleteAllRecords() {
        return $this->controller->processDeleteAllRecords();
    }

    public function processAddField() {
        return $this->controller->processAddField();
    }

    public function processEditField() {
        return $this->controller->processEditField();
    }

    public function processDeleteField() {
        return $this->controller->processDeleteField();
    }

    // CSV methods
    public function processCsvImport() {
        return $this->controller->processCsvImport();
    }

    public function processCsvExport() {
        return $this->controller->processCsvExport();
    }

    public function processCreateSchema() {
        return $this->controller->processCreateSchema();
    }

    public function processDropSchema() {
        return $this->controller->processDropSchema();
    }

    public function processRenameSchema() {
        return $this->controller->processRenameSchema();
    }

    // Delegate getters to controller for backward compatibility
    public function getInsertSuccess() { return $this->controller->getInsertSuccess(); }
    public function getInsertError() { return $this->controller->getInsertError(); }
    public function getDeleteSuccessCount() { return $this->controller->getDeleteSuccessCount(); }
    public function getDeleteError() { return $this->controller->getDeleteError(); }
    public function getEditSuccess() { return $this->controller->getEditSuccess(); }
    public function getEditError() { return $this->controller->getEditError(); }
    public function getCsvImportSuccess() { return $this->controller->getCsvImportSuccess(); }
    public function getCsvImportError() { return $this->controller->getCsvImportError(); }
    public function getCsvExportSuccess() { return $this->controller->getCsvExportSuccess(); }
    public function getCsvExportError() { return $this->controller->getCsvExportError(); }

    // Add method to load edit row data
    public function loadEditRowData($rowId, $primaryKeyColumn) {
        return $this->controller->loadEditRowData($rowId, $primaryKeyColumn);
    }

    public function getEditRowData() {
        return $this->controller->getEditRowData();
    }

    // Property access delegation for backward compatibility
    public function __get($property) {
        // First try to use getter method if it exists
        $getterMethod = 'get' . ucfirst($property);
        if (method_exists($this->controller, $getterMethod)) {
            return $this->controller->$getterMethod();
        }

        // Fall back to direct property access if property exists and is accessible
        if (property_exists($this->controller, $property)) {
            $reflection = new \ReflectionProperty($this->controller, $property);
            if ($reflection->isPublic()) {
                return $this->controller->$property;
            }
        }
        return null;
    }
}



use App\Main;

// Create main dashboard with stored connection data
$connectionData = [
    'host' => $_SESSION['host'] ?? 'localhost',
    'port' => $_SESSION['port'] ?? 5432,
    'database' => $_SESSION['database'] ?? 'postgres',
    'username' => $_SESSION['username'],
    'password' => $_SESSION['password']
];

$main = new Main($connectionData);

// Process any form submissions that might require redirects
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'do_insert') {
        $success = $main->processInsertForm();
        if ($success) {
            // Redirect to success page
            header('Location: ' . $_SERVER['REQUEST_URI'] . '&inserted=1');
            exit;
        }
    } elseif ($_POST['action'] === 'edit_row') {
        $success = $main->processEditForm();
        if ($success) {
            // Redirect with success message
            header('Location: ' . $_SERVER['REQUEST_URI'] . '&edited=1');
            exit;
        }
    } elseif ($_POST['action'] === 'delete_rows') {
        $success = $main->processDeleteRows();
        if ($success) {
            // Redirect with success message
            $redirectUrl = preg_replace('/&deleted=\d+/', '', $_SERVER['REQUEST_URI']);
            header('Location: ' . $redirectUrl . '&deleted=' . $main->deleteSuccessCount);
            exit;
        }
    } elseif ($_POST['action'] === 'drop_table') {
        $success = $main->processDropTable();
        // Redirect back to database overview after drop
        $dbParam = isset($_GET['db']) ? '?db=' . urlencode($_GET['db']) : '';
        header('Location: main.php' . $dbParam);
        exit;
    } elseif ($_POST['action'] === 'create_table') {
        $success = $main->processCreateTable();
        // Redirect back to database overview after create
        $dbParam = isset($_GET['db']) ? '?db=' . urlencode($_GET['db']) : '';
        header('Location: main.php' . $dbParam);
        exit;
    } elseif ($_POST['action'] === 'run_sql') {
        $success = $main->processSqlQuery();
        // Always redirect back to show results
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } elseif ($_POST['action'] === 'delete_all_records') {
        $success = $main->processDeleteAllRecords();
        if ($success) {
            // Redirect with success message
            $redirectUrl = preg_replace('/&deleted=\d+/', '', $_SERVER['REQUEST_URI']);
            header('Location: ' . $redirectUrl . '&deleted=' . $main->deleteSuccessCount);
            exit;
        }
    } elseif ($_POST['action'] === 'add_field') {
        $success = $main->processAddField();
        // Refresh the structure page to show the new field
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } elseif ($_POST['action'] === 'edit_field') {
        $success = $main->processEditField();
        // Refresh the structure page to show the updated field
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } elseif ($_POST['action'] === 'delete_field') {
        $success = $main->processDeleteField();
        // Refresh the structure page to show the updated field
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } elseif ($_POST['action'] === 'import_csv') {
        $success = $main->processCsvImport();
        if ($success) {
            // Redirect with success message
            header('Location: ' . $_SERVER['REQUEST_URI'] . '&imported=1');
            exit;
        }
    } elseif ($_POST['action'] === 'export_csv') {
        $success = $main->processCsvExport();
        // Note: processCsvExport will exit automatically after sending the file
    } elseif ($_POST['action'] === 'create_schema') {
        $success = $main->processCreateSchema();
        if ($success) {
            // Redirect back to database overview after create
            $dbParam = isset($_GET['db']) ? '?db=' . urlencode($_GET['db']) : '';
            header('Location: main.php' . $dbParam);
            exit;
        }
    } elseif ($_POST['action'] === 'drop_schema') {
        $success = $main->processDropSchema();
        if ($success) {
            // Redirect back to database overview after drop
            $dbParam = isset($_GET['db']) ? '?db=' . urlencode($_GET['db']) : '';
            header('Location: main.php' . $dbParam);
            exit;
        }
    } elseif ($_POST['action'] === 'rename_schema') {
        $success = $main->processRenameSchema();
        if ($success) {
            // Redirect back to database overview after rename
            $dbParam = isset($_GET['db']) ? '?db=' . urlencode($_GET['db']) : '';
            header('Location: main.php' . $dbParam);
            exit;
        }
    }
}

// Handle GET requests for delete action (direct URL access)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    // This will be handled by the Main class constructor and loadTableAction()
    // The UI will show the delete confirmation interface
}

echo $main->getHtml();

