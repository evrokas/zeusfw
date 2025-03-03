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
        if(!self::$pdo) {
            self::Connect();
        }

        return (self::$pdo);
    }

    static function prepare(string $query, array $options = []): PDOStatement|false {
        return self::$pdo->prepare($query, $option);
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


/*
 * $pdo = new PDO('mysql:host=localhost;dbname=testdb', 'username', 'password');
 * $query = new QueryBuilder($pdo);
 *
 * $results = $query->table('users')
 *     ->where('age', '>=', 18)
 *     ->where('status', '=', 'active', 'AND')
 *     ->whereIn('role', ['admin', 'editor'], false, 'OR')
 *     ->orderBy('created_at', 'DESC')
 *     ->limit(10, 0)
 *     ->get();
 * 
 * print_r($results);
 * 
 * SELECT * FROM `users` 
 * WHERE age >= :param0 AND status = :param1 OR role IN (:param2, :param3) 
 * ORDER BY created_at DESC 
 * LIMIT 0, 10
 * 
 */

class dbQuery {
    private PDO $pdo;
    private string $table = '';
    private string $className = '';
    private array $conditions = [];
    private array $params = [];
    private array $orderBy = [];
    private string $limit = '';

    public function __construct(PDO $pdo, string $table = null, $called_class_name) {
        $this->pdo = $pdo;
        $this->table = $table;
        $this->className = $called_class_name;
    }

    public function table(string $table): self {
        $this->table = $table;
        return $this;
    }

    public function where(string $column, string $operator, mixed $value, string $logic = 'AND'): self {
        $placeholder = ":param" . count($this->params);
        $this->conditions[] = ($this->conditions ? $logic . " " : "") . "$column $operator $placeholder";
        $this->params[$placeholder] = $value;
        return $this;
    }

    public function whereEq(string $column, mixed $value, string $logic = 'AND'): self {
        return $this->where($column, '=', $value, $logic);
    }

    public function whereEqGt(string $column, mixed $value, string $logic = 'AND'): self {
        return $this->where($column, '>=', $value, $logic);
    }

    public function whereEqLt(string $column, mixed $value, string $logic = 'AND'): self {
        return $this->where($column, '<=', $value, $logic);
    }

    public function whereLike(string $column, string $value, string $logic = 'AND'): self {
        return $this->where($column, 'LIKE', "%$value%", $logic);
    }

    public function whereInArray(string $column, array $values, bool $not = false, string $logic = 'AND'): self {
        if (empty($values)) return $this;
        
        $placeholders = [];
        foreach ($values as $index => $value) {
            $placeholder = ":param" . (count($this->params) + $index);
            $placeholders[] = $placeholder;
            $this->params[$placeholder] = $value;
        }
        
        $notStr = $not ? "NOT " : "";
        $this->conditions[] = ($this->conditions ? $logic . " " : "") . "$column $notStr IN (" . implode(", ", $placeholders) . ")";
        return $this;
    }

    public function whereIn(string $column, array $values, string $logic = 'AND'): self {
        return $this->whereInArray($column, $values, false, $logic);
    }

    public function whereNotIn(string $column, array $values, string $logic = 'AND'): self {
        return $this->whereInArray($column, $values, true, $logic);
    }

    public function orderBy(string|array $column, string|array $direction = 'ASC'): self {
        if(is_array($column)) {
            foreach ($column as $index => $col) {
                $dir = $direction[$index] ?? 'ASC';
                $this->orderBy[] = "$col $dir";
            }
            return $this;
        } else {
            $this->orderBy[] = "$column $direction";
        }

        return $this;
    }

    public function limit(int $limit, int $offset = 0): self {
        $this->limit = "LIMIT $offset, $limit";
        return $this;
    }

    public function prepare() {
        $sql = "SELECT * FROM `$this->table`";
        if ($this->conditions) {
            $sql .= " WHERE " . implode(" ", $this->conditions);
        }
        if ($this->orderBy) {
            $sql .= " ORDER BY " . implode(",", $this->orderBy);
        }
        if ($this->limit) {
            $sql .= " " . $this->limit;
        }
        return $sql;
    }

    // return assosiative array
    public function get(): array {
        $sql = $this->prepare();

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // return array of classes
    public function cget(): array {
        $sql = $this->prepare();

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->fetchAll(PDO::FETCH_CLASS, $this->className);
    }

    public function toSql(): string {
        $sql = "SELECT * FROM `$this->table`";
        if ($this->conditions) {
            $sql .= " WHERE " . implode(" ", $this->conditions);
        }
        if ($this->orderBy) {
            $sql .= " ORDER BY " . implode(",", $this->orderBy);
        }
        if ($this->limit) {
            $sql .= " " . $this->limit;
        }
        return $sql;
    }

    public function getParams(): array {
        return $this->params;
    }
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
    static function sgetAllFilter($tableName, $filterArray = [], $sortsArray = [], $conditionals = []) {
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
            if(empty($conditionals) || !isset($conditionals[$key])) {
                $whereList[] = "{$key} = :{$key}";
            } else {
                $whereList[] = "{$key} " . $conditionals[$key] . " :{$key}";
            }
        }

        // echopre("filterArray: " . print_r($filterArray, 1));
        // echopre("Conditionals: " . print_r($conditionals, 1));
        // echopre("whereList: " . print_r($whereList, 1));

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

    abstract static function table();

    function getid() { return ( $this->id ); }
    function setid($aid) { $this->id = $aid; }


    function query() {
        return new dbQuery(dbConnection::getConnection(), $this->_table, get_called_class());
    }

    static function squery() {
        return new dbQuery(dbConnection::getConnection(), static::table(), get_called_class());
    }
};

