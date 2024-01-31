<?php
// class info

require_once("../db/dbal.php");
class info extends dbAbstractEntityClass {
  private $name;
  private $age;
  private $email;
  function __construct($adata = array() ) {
      parent::__construct('info', $adata);
      $this->loadFields( $adata );
  }
  function loadFields($adata) {
      parent::loadFields($adata);
      if(isset($adata['name']))$this->name = $adata['name'];
      if(isset($adata['age']))$this->age = $adata['age'];
      if(isset($adata['email']))$this->email = $adata['email'];
  }
  function setname( $aname ) { $this->name = $aname; }
  function getname() { return ( $this->name); }
  function setage( $aage ) { $this->age = $aage; }
  function getage() { return ( $this->age); }
  function setemail( $aemail ) { $this->email = $aemail; }
  function getemail() { return ( $this->email); }
    function insert() {
        if($this->id != null) {
            echo 'Trying to insert() a record that already exists';
            return (null);
        }

        if(!$this->getConnection()->isConnected()) {
            if(!$this->getConnection()->Connect()) {
                echo 'Could not connect to database';
                return (null);
            }
        }
        
$sql = "INSERT INTO info ( name,age,email ) VALUES ( :name,:age,:email );";
$st = $this->getConnection()->getConnection()->prepare ( $sql );
$st->bindValue( ":name", $this->name, PDO::PARAM_STR );
$st->bindValue( ":age", $this->age, PDO::PARAM_STR );
$st->bindValue( ":email", $this->email, PDO::PARAM_STR );
$st->execute();
$this->setid( $this->getConnection()->getConnection()->lastInsertId() );
}

        function update() {
            if($this->id == null) {
                echo 'Trying to update() a record that does not exist';
                return (null);
            }
    
            if(!$this->getConnection()->isConnected()) {
                if(!$this->getConnection()->Connect()) {
                    echo 'Could not connect to database';
                    return (null);
                }
            }
                
            $sql = "UPDATE info SET name=:name,age=:age,email=:email WHERE id=:id";

            $st = $this->getConnection()->getConnection()->prepare ( $sql );
            
          $st->bindValue( ":name", $this->name, PDO::PARAM_STR );
          $st->bindValue( ":age", $this->age, PDO::PARAM_STR );
          $st->bindValue( ":email", $this->email, PDO::PARAM_STR );
          $st->bindValue( ":id", $this->id, PDO::PARAM_INT );
          $st->execute();
        }
            

        function delete() {
        if($this->id == null) {
            echo 'Trying to delete() an empty record';
            return (null);
        }
        
        if(!$this->getConnection()->isConnected()) {
            if(!$this->getConnection()->Connect()) {
                echo 'Could not connect to database';
                return (null);
            }
        }
        
$sql = "DELETE FROM info WHERE id = :id;";
$st = $this->getConnection()->getConnection()->prepare($sql);
$st->bindValue( ":id", $this->id, PDO::PARAM_INT );
$st->execute();

        return (true);
        }
    
    }