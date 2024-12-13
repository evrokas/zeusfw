<?php

// database abstract layer
class dbConnection {
    private $host;
    private $username;
    private $password;
    private $database;
    private $_connection;

    function __construct($ahost, $ausername, $apassword, $adatabase) {
        $this->host = $ahost;
        $this->username = $ausername;
        $this->password = $apassword;
        $this->database = $adatabase;

        $this->setConnection( null );
    }


    function isConnected() {
        return ($this->getConnection() != false);
    }

    function getConnection() {
        return ($this->_connection);
    }
    function setConnection($aconn) {
        $this->_connection = $aconn;
    }

    function Connect() {
        $this->_connection = new PDO("mysql:host=".$this->host.";dbname=".$this->database, $this->username, $this->password);
      return($this->_connection );
    }
};

// configuration is included from parent script
// require_once(__DIR__ . '/../../config/db.php');
if(defined('DB_HOST')) {
    $AppDBConnection = new dbConnection(DB_HOST, DB_USER, DB_PASS, DB_NAME);
}

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
    
    function getConnection() { return $GLOBALS['AppDBConnection']; }

    function getById(int $aid) {
        if(!$this->getConnection()->isConnected()) {
            // echo "Database is not connected!\n";
            if(!$this->getConnection()->Connect()) {
                echo "Could not connect to database";
                return (null);
            }
        }

        $sql = "SELECT * FROM " . $this->_table . " WHERE id=:id";
        $st = $this->getConnection()->getConnection()->prepare( $sql );
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
        if(!$this->getConnection()->isConnected()) {
            // echo "Database is not connected!\n";
            if(!$this->getConnection()->Connect()) {
                echo "Could not connect to database";
                return (null);
            }
        }

        $sql = "SELECT * FROM " . $this->_table . ";";
        $st = $this->getConnection()->getConnection()->prepare( $sql );
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

        if(!$this->getConnection()->isConnected()) {
            // echo "Database is not connected!\n";
            if(!$this->getConnection()->Connect()) {
                echo "Could not connect to database";
                return (null);
            }
        }
            
        $sql = "INSERT INTO ".$this->_table ." (username, password, email) VALUES ( " .
            ":username, :password, :email );";

        echo "SQL: $sql \n";

        $st = $this->getConnection()->getConnection()->prepare ( $sql );
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

        if(!$this->getConnection()->isConnected()) {
            // echo "Database is not connected!\n";
            if(!$this->getConnection()->Connect()) {
                echo "Could not connect to database";
                return (null);
            }
        }
            
        $sql = "UPDATE ".$this->_table ." SET username=:username, password=:password, email=:email WHERE id=:id;";

        echo "SQL: $sql \n";

        $st = $this->getConnection()->getConnection()->prepare ( $sql );
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

        if(!$this->getConnection()->isConnected()) {
            // echo "Database is not connected!\n";
            if(!$this->getConnection()->Connect()) {
                echo "Could not connect to database";
                return (null);
            }
        }
    
        $sql = "DELETE FROM " . $this->_table . " WHERE id = :id;";
        $st = $this->getConnection()->getConnection()->prepare($sql);
        $st->bindValue(":id", $this->id, PDO::PARAM_INT);
        $st->execute();

        return (true);
    }
}
