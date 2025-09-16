<?php

namespace App;

class MainController
{
    private $pgsql;
    private $selectedDatabase;
    private $selectedTable;
    private $selectedAction;
    private $currentPage;
    private $perPage;
    private $tableStructure;
    private $tableData;
    private $databases;
    private $tables;
    private $insertError;
    private $insertSuccess;
    private $deleteError;
    private $deleteSuccessCount;

    public function __construct($connectionData)
    {
        $this->pgsql = new Pgsql();

        // Initialize properties from URL parameters or defaults
        $this->selectedDatabase = $_GET['db'] ?? $connectionData['database'] ?? 'postgres';
        $this->selectedTable = $_GET['table'] ?? null;
        $this->selectedAction = $_GET['action'] ?? 'select';
        $this->currentPage = (int)($_GET['page'] ?? 1);
        $this->perPage = 25; // Items per page for data browsing

        // Initialize success/error states from URL parameters
        $this->insertSuccess = isset($_GET['inserted']);
        $this->deleteSuccessCount = isset($_GET['deleted']) ? (int)$_GET['deleted'] : 0;

        $this->insertError = null;
        $this->deleteError = null;

        // Load initial data
        $this->loadDatabases();
        $this->loadTables();

        if ($this->selectedTable) {
            $this->loadTableStructure();
            $this->loadTableAction();
        }
    }

    public function getSelectedDatabase() { return $this->selectedDatabase; }
    public function getSelectedTable() { return $this->selectedTable; }
    public function getSelectedAction() { return $this->selectedAction; }
    public function getCurrentPage() { return $this->currentPage; }
    public function getPerPage() { return $this->perPage; }
    public function getTableStructure() { return $this->tableStructure; }
    public function getTableData() { return $this->tableData; }
    public function getDatabases() { return $this->databases; }
    public function getTables() { return $this->tables; }
    public function getInsertError() { return $this->insertError; }
    public function getInsertSuccess() { return $this->insertSuccess; }
    public function getDeleteError() { return $this->deleteError; }
    public function getDeleteSuccessCount() { return $this->deleteSuccessCount; }

    private function loadDatabases()
    {
        try {
            $this->pgsql->connect(
                $_SESSION['host'] ?? 'localhost',
                $_SESSION['port'] ?? 5432,
                'postgres', // Connect to default postgres database to list all databases
                $_SESSION['username'],
                $_SESSION['password']
            );

            $this->databases = $this->pgsql->show_database();
        } catch (\Exception $e) {
            $this->databases = [];
        }
    }

    private function loadTables()
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

            $this->tables = $this->pgsql->show_table();
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

            $this->tableStructure = $this->pgsql->show_field($this->selectedTable);
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
            $query = "SELECT * FROM " . pg_escape_identifier($this->pgsql->getConnection(), $this->selectedTable) .
                    " ORDER BY 1 LIMIT $this->perPage OFFSET $offset";

            $this->tableData = $this->pgsql->run_query($query);
        } catch (\Exception $e) {
            $this->tableData = [];
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
                    $columns[] = pg_escape_identifier($this->pgsql->getConnection(), $fieldName);
                    $placeholders[] = 'NULL';
                } elseif (isset($_POST[$fieldName])) {
                    $value = $_POST[$fieldName];

                    // Handle checkbox values
                    if ($fieldType === 'boolean') {
                        $value = $value ? 'true' : 'false';
                    }

                    $columns[] = pg_escape_identifier($this->pgsql->getConnection(), $fieldName);
                    $values[] = $value;
                    $placeholders[] = '$' . $paramIndex;
                    $paramIndex++;
                }
            }

            if (empty($columns)) {
                throw new \Exception('No valid fields to insert');
            }

            $query = "INSERT INTO " . pg_escape_identifier($this->pgsql->getConnection(), $this->selectedTable) .
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
                    $query = "DELETE FROM " . pg_escape_identifier($this->pgsql->getConnection(), $this->selectedTable) .
                            " WHERE " . pg_escape_identifier($this->pgsql->getConnection(), $primaryKeyColumn) . " = $1";

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
            $countQuery = "SELECT COUNT(*) as total FROM " . pg_escape_identifier($this->pgsql->getConnection(), $this->selectedTable);
            $countResult = $this->pgsql->run_query($countQuery);
            $rowCount = pg_fetch_result($countResult, 0, 'total');

            // Execute DELETE ALL query
            $query = "DELETE FROM " . pg_escape_identifier($this->pgsql->getConnection(), $this->selectedTable);
            $result = $this->pgsql->run_query($query);

            // Get affected row count
            $affectedRows = pg_affected_rows($result);

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
            $query = "DROP TABLE " . pg_escape_identifier($this->pgsql->getConnection(), $tableName);
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
            $sql = "CREATE TABLE " . pg_escape_identifier($this->pgsql->getConnection(), $tableName) . " (\n";

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

                $definition = pg_escape_identifier($this->pgsql->getConnection(), $columnName) . " " . $columnType;

                // Add constraints
                if (isset($column['not_null']) && $column['not_null']) {
                    $definition .= " NOT NULL";
                }

                if (isset($column['unique']) && $column['unique']) {
                    $definition .= " UNIQUE";
                }

                if (isset($column['primary']) && $column['primary']) {
                    $primaryKeys[] = pg_escape_identifier($this->pgsql->getConnection(), $columnName);
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
                $affectedRows = is_array($result) ? count($result) : 0;
                $_SESSION['sql_result'] = ['rows' => [], 'affected_rows' => $affectedRows];
            }

            return true;

        } catch (\Exception $e) {
            $_SESSION['sql_result'] = ['error' => $e->getMessage()];
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
                $html .= $this->getTablesList();
            }

            $html .= '</div>';
        }

        $html .= '</div></nav>';
        return $html;
    }

    private function getTablesList()
    {
        $tables = $this->controller->getTables();
        $selectedTable = $this->controller->getSelectedTable();

        if (empty($tables)) return '';

        $html = '<ul class="tables-list">';

        foreach ($tables as $table) {
            $isActive = ($table === $selectedTable) ? 'active' : '';
            $html .= '<li class="table-item ' . $isActive . '">
                <a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . '&table=' . urlencode($table) . '&action=select" class="table-name">
                    <i class="fas fa-table"></i> ' . htmlspecialchars($table) . '
                </a>';

            if ($table === $selectedTable) {
                //table actions
                $html .= '<ul class="table-actions">
                    <li><a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . '&table=' . urlencode($table) . '&action=structure"><i class="fas fa-info-circle"></i></a></li>
                    <li><a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . '&table=' . urlencode($table) . '&action=select"><i class="fas fa-list"></i></a></li>
                    <li><a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . '&table=' . urlencode($table) . '&action=insert"><i class="fas fa-plus"></i></a></li>
                    <li><a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . '&table=' . urlencode($table) . '&action=delete"><i class="fas fa-trash"></i></a></li>
                    <li><a href="?db=' . urlencode($this->controller->getSelectedDatabase()) . '&table=' . urlencode($table) . '&action=drop" onclick="return confirmDropTable(\'' . htmlspecialchars($table) . '\')"><i class="fas fa-times-circle"></i></a></li>
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

        if (empty($structure)) {
            return '<div class="error">Unable to load table structure</div>';
        }

        $html = '<div class="structure-container">
            <h3>Table Structure</h3>
            <div class="structure-table-wrapper">
                <table class="structure-table">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Type</th>
                            <th>Nullable</th>
                            <th>Default</th>
                        </tr>
                    </thead>
                    <tbody>';

        foreach ($structure as $field) {
            $html .= '<tr>
                <td><strong>' . htmlspecialchars($field['name']) . '</strong></td>
                <td>' . htmlspecialchars($field['type']) . '</td>
                <td>' . ($field['nullable'] ? 'YES' : 'NO') . '</td>
                <td>' . htmlspecialchars($field['default'] ?? 'NULL') . '</td>
            </tr>';
        }

        $html .= '</tbody></table></div></div>';
        return $html;
    }

    private function getTableDataHtml()
    {
        $data = $this->controller->getTableData();
        $structure = $this->controller->getTableStructure();

        $html = $this->getInsertMessages();
        $html .= $this->getDeleteMessages();

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
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all" onchange="toggleAllCheckboxes()"></th>';

        foreach ($structure as $field) {
            $html .= '<th>' . htmlspecialchars($field['name']) . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($data as $row) {
            $firstValue = reset($row); // Get first column value for checkbox
            $html .= '<tr>
                <td><input type="checkbox" name="selected_rows[]" value="' . htmlspecialchars($firstValue) . '" onchange="updateSelectionCount()"></td>';

            foreach ($structure as $field) {
                $fieldName = $field['name'];
                $value = $row[$fieldName] ?? '';
                $displayValue = is_null($value) ? '<em>NULL</em>' : htmlspecialchars($value);
                $html .= '<td>' . $displayValue . '</td>';
            }

            $html .= '</tr>';
        }

        $html .= '</tbody></table></div></div></form>';
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
            padding: 2px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            gap: 5px;
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
        }

        .btn-danger:hover {
            background: #c0392b;
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
            return confirm(`Are you sure you want to drop the table "${tableName}"?\\n\\nThis action will permanently delete the table and all its data.\\n\\nThis cannot be undone!`);
        }

        function confirmDropTableFinal() {
            const finalConfirm = prompt(\'To confirm deletion, please type "DROP" in all caps:\');
            if (finalConfirm !== \'DROP\') {
                alert(\'Table drop cancelled. You must type "DROP" to confirm.\');
                return false;
            }
            return confirm(\'FINAL WARNING: This will permanently delete the table and ALL its data!\\n\\nClick OK to proceed or Cancel to stop.\');
        }

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

    public function processDeleteRows() {
        return $this->controller->processDeleteRows();
    }

    public function processDropTable() {
        return $this->controller->processDropTable();
    }

    public function processCreateTable() {
        return $this->controller->processCreateTable();
    }

    // Delegate getters to controller for backward compatibility
    public function getInsertSuccess() { return $this->controller->getInsertSuccess(); }
    public function getInsertError() { return $this->controller->getInsertError(); }
    public function getDeleteSuccessCount() { return $this->controller->getDeleteSuccessCount(); }
    public function getDeleteError() { return $this->controller->getDeleteError(); }

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
