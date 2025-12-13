<?php
class Database {
    private $host = "sql209.infinityfree.com";
    private $db_name = "if0_40668683_db";
    private $username = "if0_40668683";
    private $password = "JSgSGDQAS52E3N";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            // Don't show error message, just return null
            return null;
        }
        return $this->conn;
    }
}
?>