<?php

require_once('config.php');

$servername = MYSQL_SERVER_NAME;
$database = MYSQL_DATABASE_NAME;
$username = MYSQL_USER_NAME;
$password = MYSQL_USER_PASSWORD;

try {
    $conn = new PDO("mysql:host=$servername;dbname=$database", $username, $password);
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
//     echo "Connected successfully"; 
    }
catch(PDOException $e)
    {
    echo "Connection failed: " . $e->getMessage();
    die();
    }
?>