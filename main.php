<?php
// Start session if not already started - MUST be first, before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in - redirect if not authenticated
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

require_once 'src/main.php';
require_once 'src/pgsql.php';

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
    }
}

// Handle GET requests for delete action (direct URL access)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    // This will be handled by the Main class constructor and loadTableAction()
    // The UI will show the delete confirmation interface
}

echo $main->getHtml();
?>
