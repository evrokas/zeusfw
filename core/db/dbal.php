<?php

// database abstract layer
class dbConnection {
    static private $host;
    static private $username;
    static private $password;
    static private $database;
    static private $pdo;

    static function init($ahost, $ausername, $apassword, $adatabase) {
        self::$host = $ahost;
        self::$username = $ausername;
        self::$password = $apassword;
        self::$database = $adatabase;

        self::setConnection( null );
    }

    static function isConnected() {
        return (self::$pdo != false);
    }

    static function getConnection(): PDO {
        return (self::$pdo);
    }

    static function setConnection($aconn) {
        self::$pdo = $aconn;
    }

    /* connect to database and return pdo */
    static function Connect() {
        if(!self::$pdo) {
            try {
                self::$pdo = new PDO("mysql:host=".self::$host.";dbname=".self::$database, self::$username, self::$password);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                die("Database connection failed: " . $e->getMessage());
            }
        }

      return ( self::$pdo );
    }
};

// configuration is included from parent script
// require_once(__DIR__ . '/../../config/db.php');
// if(defined('DB_HOST')) {
    // $AppDBConnection = new dbConnection(DB_HOST, DB_USER, DB_PASS, DB_NAME);
// }

abstract class dbAbstractEntityClass {
    protected $_table;
    // protected $_fields;

    // table fields
    protected $id = null;

    function __construct($atable, $adata = array()) {
        $this->_table = $atable;
        $this->loadFields($adata);
    }

    function loadFields($adata) {
        if(isset($adata['id']))
            $this->id = $adata['id'];
    }

    function getFields() {
        $resp = array();

        return $resp;
    }

    function getAllFields() {
        $resp = array();
        $resp = array_merge($resp, ['id' => $this->id]);

        return $resp;
    }
    
    function getConnection() { return dbConnection::getConnection(); }
    static function sgetConnection() { return dbConnection::getConnection(); }
    function isdbConnected() { return dbConnection::isConnected(); }
    
    function dbConnect() { return dbConnection::Connect(); }
    static function sdbConnect() { return dbConnection::Connect(); }

    function getById(int $aid) {
        if(!$this->isdbConnected()) {
            // echo "Database is not connected!\n";
            if(!$this->dbConnect()) {
                echo "Could not connect to database";
                return (null);
            }
        }

        $sql = "SELECT * FROM " . $this->_table . " WHERE id=:id";
        $st = $this->getConnection()->prepare( $sql );
        $st->bindValue(":id", $aid, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();

        if($row) {
            $class = get_debug_type( $this );
            $rclass = new $class($this->_table);
            $rclass->loadFields( $row );
            return $rclass;
        } else return (null);
    }

    function getAll() {
        if(!$this->isdbConnected()) {
            // echo "Database is not connected!\n";
            if(!$this->dbConnect()) {
                echo "Could not connect to database";
                return (null);
            }
        }

        $sql = "SELECT * FROM " . $this->_table . ";";
        $st = $this->getConnection()->prepare( $sql );
        $st->execute();

        $list = array();
        $class = get_debug_type( $this );

        while( $row = $st->fetch() ) {
            $rclass = new $class($this->_table);
            $rclass->loadFields( $row );
            $list[] = $rclass;
        }

        return ($list);
    }

    // static version
    // return results based on filter, filter is on format ['id' => '0', 'name' => 'test']
    static function sgetAllFilter($tableName, $filterArray = [], $sortsArray = []) {
        if(!self::sgetConnection()) {
            echo "Database is not connected!\n";
            if(!self::sgetConnection()->Connect()) {
                echo "Could not connect to database";
                return (null);
            }
        }

        $sql = "SELECT * FROM " . $tableName;

        $whereList = [];
        foreach($filterArray as $key => $value) {
            $whereList[] = "{$key} = :{$key}";
        }

        if(!empty($whereList)) {
            $sql .= " WHERE " . implode(" AND " , $whereList);
        }

        foreach($sortsArray as $key => $direction) {
            $sql .= " ORDER BY {$key} {$direction}";
        }

        // print_r($filterArray);
        // print_r($sortsArray);
        // print_r($sql);
        // echopre(print_r($filterArray, 1));
        // echopre(print_r($sortsArray, 1));
        // echopre(print_r($sql, 1));

        $stmt = self::sgetConnection()->prepare( $sql );
        $stmt->execute( $filterArray );

        // $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = [];
        $className = get_called_class();
        while( $row = $stmt->fetch(PDO::FETCH_ASSOC) ) {
            // $rclass = new webFormsClass( $row );

            $rclass = new $className( $row );
            $results[] = $rclass;
        }
        
        // print_r($results);
        return ($results);
    }

    // insert in database this object
    abstract function insert();

    // delete object from database
    abstract function delete();

    // update database with the contents of this object
    abstract function update();


    function getid() { return ( $this->id ); }
    function setid($aid) { $this->id = $aid; }
};


class entityTest extends dbAbstractEntityClass {

    // fields
    protected $username = null;
    protected $password = null;
    protected $email = null;

    function __construct($adata = array()) {
        parent::__construct('test', $adata);

        $this->loadFields( $adata );
    }

    function loadFields($adata) {
        parent::loadFields($adata);
        if(isset($adata['username']))$this->username = $adata['username'];
        if(isset($adata['password']))$this->password = $adata['password'];
        if(isset($adata['email']))$this->email = $adata['email'];
    }

    function getusername() { return $this->username; }
    function setusername( $ausername ) { $this->username = $ausername; }

    function getpassword() { return $this->password; }
    function setpassword($apassword) { $this->password = $apassword; }

    function getemail() { return $this->email; }
    function setemail( $aemail ) { $this->email = $aemail; }

    // test functions

    function insert() {
        if($this->id != null) {
            echo "Trying to insert() a record that already exists";
            return (null);
        }

        if(!$this->isdbConnected()) {
            // echo "Database is not connected!\n";
            if(!$this->getConnection()->Connect()) {
                echo "Could not connect to database";
                return (null);
            }
        }
            
        $sql = "INSERT INTO ".$this->_table ." (username, password, email) VALUES ( " .
            ":username, :password, :email );";

        echo "SQL: $sql \n";

        $st = $this->getConnection()->prepare ( $sql );
        $st->bindValue( ":username", $this->username, PDO::PARAM_STR );
        $st->bindValue( ":password", $this->password, PDO::PARAM_STR );
        $st->bindValue( ":email", $this->email, PDO::PARAM_STR );
        $st->execute();
        
        echo "Inserted record\n";
        $this->setid( $this->getConnection()->getConnection()->lastInsertId() );
    }
    
    function update() {
        if($this->id == null) {
            echo "Trying to update() a record that does not exist";
            return (null);
        }

        if(!$this->isdbConnected()) {
            // echo "Database is not connected!\n";
            if(!$this->getConnection()->Connect()) {
                echo "Could not connect to database";
                return (null);
            }
        }
            
        $sql = "UPDATE ".$this->_table ." SET username=:username, password=:password, email=:email WHERE id=:id;";

        echo "SQL: $sql \n";

        $st = $this->getConnection()->prepare ( $sql );
        $st->bindValue( ":username", $this->username, PDO::PARAM_STR );
        $st->bindValue( ":password", $this->password, PDO::PARAM_STR );
        $st->bindValue( ":email", $this->email, PDO::PARAM_STR );
        $st->bindValue( ":id", $this->id, PDO::PARAM_INT );

        $st->execute();
        
        echo "Updated record\n";
    }

    function delete() {
        if($this->id == null) {
            echo "Trying to delete() an empty record";
            return (null);
        }

        if(!$this->isdbConnected()) {
            // echo "Database is not connected!\n";
            if(!$this->getConnection()->Connect()) {
                echo "Could not connect to database";
                return (null);
            }
        }
    
        $sql = "DELETE FROM " . $this->_table . " WHERE id = :id;";
        $st = $this->getConnection()->prepare($sql);
        $st->bindValue(":id", $this->id, PDO::PARAM_INT);
        $st->execute();

        return (true);
    }
}
