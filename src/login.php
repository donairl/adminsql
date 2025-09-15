<?php

namespace App;

class Login {
    private $pgsql;
    private $message = '';
    private $databases = [];

    public function __construct() {
        // Start session if not already started
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

?>
