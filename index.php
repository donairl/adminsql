<?php
require_once 'src/login.php';
require_once 'src/pgsql.php';

use App\Login;

// Create and run the login application
$login = new Login();
$login->processLogin();
echo $login->getHtml();
?>
