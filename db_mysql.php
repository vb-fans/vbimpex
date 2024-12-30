<?php
/*======================================================================*\
|| ####################################################################
|| # vBulletin Impex
|| # ---------------------------------------------------------------- # ||
|| # Copyright 2000-2014 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This code is made available under the Modified BSD License -- see license.txt # ||
|| # http://www.vbulletin.com 
|| ####################################################################
\*======================================================================*/

error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);

// db class for mysql
// this class is used in all scripts
// do NOT fiddle unless you know what you are doing

define('DBARRAY_NUM', MYSQLI_NUM);
define('DBARRAY_ASSOC', MYSQLI_ASSOC);
define('DBARRAY_BOTH', MYSQLI_BOTH);

if (!defined('IDIR')) { die; }

if (!defined('DB_EXPLAIN')) {
    define('DB_EXPLAIN', false);
}

if (!defined('DB_QUERIES')) {
    define('DB_QUERIES', false);
}

class DB_Sql_vb_impex
{
    private $database = '';
    private $type = 'mysqli'; // Use mysqli
    private $link_id = null;
    private $errdesc = '';
    private $errno = 0;
    private $reporterror = 1;
    private $appname = 'vBulletin';
    private $appshortname = 'vBulletin (cp)';
    
    public function connect(string $server, string $user, string $password, bool $usepconnect, string $charset = ""): bool
    {
        // Attempt to connect to the database
        $this->link_id = mysqli_connect($server, $user, $password, $this->database);

        if (!$this->link_id) {
            $this->halt('Connection failed: ' . mysqli_connect_error());
            return false;
        }

        if (!empty($charset)) {
            mysqli_set_charset($this->link_id, $charset);
        }

        return true;
    }

    public function affected_rows(): int
    {
        return mysqli_affected_rows($this->link_id);
    }

    public function geterrdesc(): string
    {
        return mysqli_error($this->link_id);
    }

    public function geterrno(): int
    {
        return mysqli_errno($this->link_id);
    }

    public function select_db(string $database): bool
    {
        if (!mysqli_select_db($this->link_id, $database)) {
            $this->halt('Cannot use database ' . $database);
            return false;
        }

        $this->database = $database;
        return true;
    }

    public function query(string $query_string)
    {
        $result = mysqli_query($this->link_id, $query_string);

        if (!$result) {
            $this->halt('Invalid SQL: ' . $query_string);
            return false;
        }

        return $result;
    }

    public function fetch_array($query_id, int $type = DBARRAY_BOTH): array
    {
        return mysqli_fetch_array($query_id, $type);
    }

    public function free_result($query_id): bool
    {
        return mysqli_free_result($query_id);
    }

    public function query_first(string $query_string, int $type = DBARRAY_BOTH): array
    {
        $query_id = $this->query($query_string);
        $returnarray = $this->fetch_array($query_id, $type);
        $this->free_result($query_id);
        return $returnarray;
    }

    public function num_rows($query_id): int
    {
        return mysqli_num_rows($query_id);
    }

    public function insert_id(): int
    {
        return mysqli_insert_id($this->link_id);
    }

    public function close(): bool
    {
        return mysqli_close($this->link_id);
    }

    public function escape_string(string $string): string
    {
        return mysqli_real_escape_string($this->link_id, $string);
    }

    public function halt(string $msg): void
    {
        $this->errdesc = mysqli_error($this->link_id);
        $this->errno = mysqli_errno($this->link_id);

        // prints warning message when there is an error
        if ($this->reporterror == 1) {
            $message = 'ImpEx Database error: ' . $msg . "\n";
            $message .= 'MySQL error: ' . $this->errdesc . "\n";
            $message .= 'Error number: ' . $this->errno . "\n";
            $message .= 'Date: ' . date('l dS of F Y h:i:s A') . "\n";
            $message .= 'Database: ' . $this->database . "\n";

            echo "<html><head><title>ImpEx Database Error</title></head><body>";
            echo "<blockquote><p><b>There seems to have been a problem with the database.</b><br />";
            echo "<pre>" . htmlspecialchars($message) . "</pre></blockquote>";
            echo "</body></html>";
            exit;
        }
    }
}

/*======================================================================*/
?>