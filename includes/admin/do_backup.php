<?php
ob_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/config.php';
require_admin_auth();
ob_end_clean();

header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');
set_error_handler(function($errno, $errstr) { return true; });

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    $dbHost    = defined('DB_HOST') ? DB_HOST : 'localhost';
    $dbUser    = defined('DB_USER') ? DB_USER : 'root';
    $dbPass    = defined('DB_PASS') ? DB_PASS : '';
    $dbName    = defined('DB_NAME') ? DB_NAME : 'plant';

    $backupDir = '/opt/lampp/htdocs/plant/database/';

    // Create folder with full permissions if not exists
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0777, true);
        chmod($backupDir, 0777);
    }

    if (!is_dir($backupDir)) {
        throw new Exception('Backup directory does not exist and could not be created: ' . $backupDir);
    }

    if (!is_writable($backupDir)) {
        throw new Exception('Backup directory is not writable: ' . $backupDir . ' — Run: chmod 777 /opt/lampp/htdocs/plant/database');
    }

    $filename = $dbName . '_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backupDir . $filename;

    $mysqli = @new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($mysqli->connect_error) {
        throw new Exception('Database connection failed: ' . $mysqli->connect_error);
    }
    $mysqli->set_charset('utf8mb4');

    $output  = "-- ============================================================\n";
    $output .= "-- Plant Database Backup\n";
    $output .= "-- Generated : " . date('Y-m-d H:i:s') . "\n";
    $output .= "-- Database  : {$dbName}\n";
    $output .= "-- ============================================================\n\n";
    $output .= "SET NAMES utf8mb4;\n";
    $output .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    $tables = [];
    $result = $mysqli->query("SHOW TABLES");
    if (!$result) {
        throw new Exception('Could not retrieve tables: ' . $mysqli->error);
    }
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    if (empty($tables)) {
        throw new Exception('No tables found in database "' . $dbName . '".');
    }

    foreach ($tables as $table) {
        $createRes = $mysqli->query("SHOW CREATE TABLE `{$table}`");
        if (!$createRes) {
            throw new Exception("Could not get CREATE for `{$table}`: " . $mysqli->error);
        }
        $createRow  = $createRes->fetch_assoc();
        $createStmt = $createRow['Create Table'];

        $output .= "-- ----------------------------------------------------------\n";
        $output .= "-- Table: `{$table}`\n";
        $output .= "-- ----------------------------------------------------------\n";
        $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $output .= $createStmt . ";\n\n";

        $dataRes = $mysqli->query("SELECT * FROM `{$table}`");
        if (!$dataRes) {
            throw new Exception("Could not select from `{$table}`: " . $mysqli->error);
        }

        if ($dataRes->num_rows > 0) {
            $output .= "INSERT INTO `{$table}` VALUES\n";
            $rows = [];
            while ($row = $dataRes->fetch_row()) {
                $vals = [];
                foreach ($row as $val) {
                    $vals[] = ($val === null) ? 'NULL' : "'" . $mysqli->real_escape_string($val) . "'";
                }
                $rows[] = '(' . implode(', ', $vals) . ')';
            }
            $output .= implode(",\n", $rows) . ";\n\n";
        }
        $dataRes->free();
    }

    $output .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    $output .= "-- End of backup\n";

    $mysqli->close();

    if (file_put_contents($filepath, $output) === false) {
        throw new Exception('Failed to write file. Run: chmod 777 /opt/lampp/htdocs/plant/database');
    }

    $sizeKB = round(filesize($filepath) / 1024, 2);

    echo json_encode([
        'success'  => true,
        'filename' => $filename,
        'path'     => '/plant/database/' . $filename,
        'size'     => $sizeKB . ' KB',
        'tables'   => count($tables),
        'message'  => 'Backup created successfully.',
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}