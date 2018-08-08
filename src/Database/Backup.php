<?php
/**
 * This file contains the Backup_Database class wich performs
 * a partial or complete backup of any given MySQL database
 * @author Daniel López Azaña <daniloaz@gmail.com>
 * @version 1.0
 */

namespace Database;

/**
 * The Backup_Database class
 */
class Backup
{
    /**
     * Host where the database is located
     */
    var $host;

    /**
     * Username used to connect to database
     */
    var $username;

    /**
     * Password used to connect to database
     */
    var $passwd;

    /**
     * Database to backup
     */
    var $dbName;

    /**
     * Database charset
     */
    var $charset;

    /**
     * Database connection
     */
    var $conn;

    /**
     * Backup directory where backup files are stored
     */
    var $backupDir;

    /**
     * Output backup file
     */
    var $backupFile;

    /**
     * Use gzip compression on backup file
     */
    var $gzipBackupFile;

    /**
     * Content of standard output
     */
    var $output;

    /**
     * Constructor initializes database
     */
    public function __construct($host, $username, $passwd, $dbName, $charset = 'utf8')
    {
        $this->host = $host;
        $this->username = $username;
        $this->passwd = $passwd;
        $this->dbName = $dbName;
        $this->charset = $charset;
        $this->conn = $this->initializeDatabase();
        $this->backupDir = BACKUP_DIR ? BACKUP_DIR : '.';
        $this->backupFile = $this->dbName . '-' . date("Ymd_His", time()) . '.sql';
        $this->gzipBackupFile = defined('GZIP_BACKUP_FILE') ? GZIP_BACKUP_FILE : true;
        $this->output = '';
    }

    protected function initializeDatabase()
    {
        try {
            $conn = mysqli_connect($this->host, $this->username, $this->passwd, $this->dbName);
            if (mysqli_connect_errno()) {
                throw new Exception('ERROR connecting database: ' . mysqli_connect_error());
                die();
            }
            if (!mysqli_set_charset($conn, $this->charset)) {
                mysqli_query($conn, 'SET NAMES ' . $this->charset);
            }
        } catch (Exception $e) {
            print_r($e->getMessage());
            die();
        }

        return $conn;
    }

    /**
     * Backup the whole database or just some tables
     * Use '*' for whole database or 'table1 table2 table3...'
     * @param string $tables
     */
    public function backupTables($tables = '*')
    {
        try {
            $this->obfPrint('Start backup `' . $this->dbName . '`', 1, 1);

            /**
             * Tables to export
             */
            if ($tables == '*') {
                $tables = array();
                $result = mysqli_query($this->conn, 'SHOW TABLES');
                while ($row = mysqli_fetch_row($result)) {
                    $tables[] = $row[0];
                }
            } else {
                $tables = is_array($tables) ? $tables : explode(',', str_replace(' ', '', $tables));
            }

            $sql = 'CREATE DATABASE IF NOT EXISTS `' . $this->dbName . "`;\n\n";
            $sql .= 'USE `' . $this->dbName . "`;\n\n";

            /**
             * Iterate tables
             */
            foreach ($tables as $table) {
                $this->obfPrint("Backing up `" . $table . "` table..." . str_repeat('.', 50 - strlen($table)), 0, 0);

                /**
                 * CREATE TABLE
                 */
                $sql .= 'DROP TABLE IF EXISTS `' . $table . '`;';
                $row = mysqli_fetch_row(mysqli_query($this->conn, 'SHOW CREATE TABLE `' . $table . '`'));
                $sql .= "\n\n" . $row[1] . ";\n\n";

                /**
                 * INSERT INTO
                 */
                $row = mysqli_fetch_row(mysqli_query($this->conn, 'SELECT COUNT(*) FROM `' . $table . '`'));
                $numRows = $row[0];

                // Split table in batches in order to not exhaust system memory 
                $batchSize = 1000; // Number of rows per batch
                $numBatches = intval($numRows / $batchSize) + 1; // Number of while-loop calls to perform
                for ($b = 1; $b <= $numBatches; $b++) {

                    $query = 'SELECT * FROM `' . $table . '` LIMIT ' . ($b * $batchSize - $batchSize) . ',' . $batchSize;
                    $result = mysqli_query($this->conn, $query);
                    $numFields = mysqli_num_fields($result);

                    for ($i = 0; $i < $numFields; $i++) {
                        $rowCount = 0;
                        while ($row = mysqli_fetch_row($result)) {
                            $sql .= 'INSERT INTO `' . $table . '` VALUES(';
                            for ($j = 0; $j < $numFields; $j++) {
                                if (isset($row[$j])) {
                                    $row[$j] = addslashes($row[$j]);
                                    $row[$j] = str_replace("\n", "\\n", $row[$j]);
                                    $sql .= '"' . $row[$j] . '"';
                                } else {
                                    $sql .= 'NULL';
                                }

                                if ($j < ($numFields - 1)) {
                                    $sql .= ',';
                                }
                            }

                            $sql .= ");\n";
                        }
                    }

                    $this->saveFile($sql);
                    $sql = '';
                }

                $sql .= "\n\n\n";

                $this->obfPrint(" OK");
            }

            $backupFile = $this->backupFile;

            // 如果需要压缩
            if ($this->gzipBackupFile) {
                $backupFile = $this->gzipBackupFile($backupFile) ?: $backupFile;
            }
        } catch (Exception $e) {
            print_r($e->getMessage());
            $this->obfPrint('Backup file fail !', 1, 1);
            return false;
        }

        $this->obfPrint('Backup file succesfully saved to ' . $this->backupDir . '/' . $backupFile, 1, 2);
        return $backupFile;
    }

    /**
     * Save SQL to file
     * @param string $sql
     */
    protected function saveFile(&$sql)
    {
        if (!$sql) return false;

        try {

            if (!file_exists($this->backupDir)) {
                mkdir($this->backupDir, 0777, true);
            }

            file_put_contents($this->backupDir . '/' . $this->backupFile, $sql, FILE_APPEND | LOCK_EX);

        } catch (Exception $e) {
            print_r($e->getMessage());
            return false;
        }

        return true;
    }

    /*
     * Gzip backup file
     *
     * @param string Old filename
     * @param integer $level GZIP compression level (default: 9)
     * @return string New filename (with .gz appended) if success, or false if operation fails
     */
    protected function gzipBackupFile($filename, $level = 9)
    {
        $source = $this->backupDir . '/' . $filename;
        $dest = $source . '.gz';

        $this->obfPrint('Gzipping backup file to ' . $dest . '... ', 1, 0);

        $mode = 'wb' . $level;
        if ($fpOut = gzopen($dest, $mode)) {
            if ($fpIn = fopen($source, 'rb')) {
                while (!feof($fpIn)) {
                    gzwrite($fpOut, fread($fpIn, 1024 * 256));
                }
                fclose($fpIn);
            } else {
                $this->obfPrint('GZIP Fail');
                return false;
            }
            gzclose($fpOut);
            if (!unlink($source)) {
                $this->obfPrint('GZIP Fail');
                return false;
            }
        } else {
            $this->obfPrint('GZIP Fail');
            return false;
        }

        $this->obfPrint('GZIP OK');
        return $filename . '.gz';
    }

    /**
     * Prints message forcing output buffer flush
     *
     */
    public function obfPrint($msg = '', $lineBreaksBefore = 0, $lineBreaksAfter = 1)
    {
        if (!$msg) {
            return false;
        }

        $output = '';

        if (php_sapi_name() != "cli") {
            $lineBreak = "<br />";
        } else {
            $lineBreak = "\n";
        }

        if ($lineBreaksBefore > 0) {
            for ($i = 1; $i <= $lineBreaksBefore; $i++) {
                $output .= $lineBreak;
            }
        }

        $output .= $msg;

        if ($lineBreaksAfter > 0) {
            for ($i = 1; $i <= $lineBreaksAfter; $i++) {
                $output .= $lineBreak;
            }
        }


        // Save output for later use
        $this->output .= str_replace('<br />', '\n', $output);

        echo $output;


        if (php_sapi_name() != "cli") {
            ob_flush();
        }

        $this->output .= " ";

        flush();
    }

    /**
     * Returns full execution output
     *
     */
    public function getOutput()
    {
        return $this->output;
    }
}
