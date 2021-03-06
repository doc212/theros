<?php

namespace AppBundle\Entity;
use Doctrine\DBAL\Driver\Connection as Db;

class Teacher
{
    public $id;
    public $fullname;
    public $password;
    public $passwordChanged;
    private $roles = array();
    public function __construct($row)
    {
        $this->id = $row["tea_id"];
        $this->fullname = $row["tea_fullname"];
        $this->password = $row["tea_password"];
        $this->passwordChanged = (bool)$row["tea_pwd_changed"];
    }

    /**
     * Create a Teacher instance from a db row (assoc array) or null if the tea_id is not found
     */
    public static function FromRow($row)
    {
        if(isset($row["tea_id"]) && $row["tea_id"]){
            return new Teacher($row);
        } else {
            return NULL;
        }
    }

    /**
     * Get a list of all teachers using the given database connection
     */
    public static function GetAll(Db $db, $withRoles = FALSE)
    {
        $list=[];
        $result=$db->query("
            SELECT *
            FROM teacher
            ORDER BY tea_fullname
        ")->fetchAll();
        foreach($result as $row){
            $list[]=self::FromRow($row);
        }
        if ($withRoles) {
            $byId = array();
            foreach ($list as $t) {
                $byId[$t->id] = $t;
            }
            $result = $db->query(" SELECT tr_tea_id, ro_role FROM teacher_role JOIN role ON ro_id = tr_ro_id ")->fetchAll();
            foreach ($result as $row) {
                $byId[$row["tr_tea_id"]]->addRole($row["ro_role"]);
            }
        }
        return $list;
    }

    /**
     * Return a single Teacher instance from its id or NULL if not found
     */
    public static function GetById(Db $db, $id)
    {
        $s=$db->prepare("SELECT * FROM teacher WHERE tea_id = :id");
        $s->bindValue("id", $id, \PDO::PARAM_INT);
        $s->execute();
        return self::FromRow($s->fetch());
    }

    /**
     * Set the teacher's password and flag it as initd
     */
    public function initPassword(Db $db, $password)
    {
        $s=$db->prepare("
            UPDATE teacher SET
            tea_password = :password
            , tea_pwd_changed = 1
            WHERE tea_id = :id
        ");
        $s->bindValue("password", md5($password), \PDO::PARAM_STR);
        $s->bindValue("id", $this->id, \PDO::PARAM_INT);
        $s->execute();
        $this->passwordChanged = TRUE;
    }

    /**
     * Return an array of Subject instances teached by $this Teacher in the given $schoolyear (given by description)
     * in the given class
     */
    public function getSubjects(Db $db, $schoolyear, $classId)
    {
        return Subject::GetByTeacherAndClass($db, $schoolyear, $this->id, $classId);
    }

    /**
     * Populate the roles array from db.
     * After this, hasRole can be called.
     */
    public function getRoles(Db $db)
    {
        $query= "SELECT ro_role "
            ." FROM role "
            ." JOIN teacher_role "
            ." ON ro_id = tr_ro_id "
            ." WHERE tr_tea_id = :id"
        ;
        $s=$db->prepare($query);
        $s->bindValue("id", $this->id, \PDO::PARAM_INT);
        $s->execute();
        $result = $s->fetchAll();
        foreach ($result as $row) {
            $this->addRole($row["ro_role"]);
        }
    }

    private function addRole($role) {
        $this->roles[] = $role;
        if ($role == Role::ADMIN) {
            $this->_isAdmin = TRUE;
        }
    }

    private $_isAdmin = FALSE;
    public function isAdmin()
    {
        return $this->_isAdmin;
    }

    public function setAdmin(Db $db, $admin)
    {
        if ($admin) {
            $query=
                " INSERT INTO teacher_role(tr_tea_id, tr_ro_id) "
                ." SELECT :id, ro_id "
                ." FROM role "
                ." WHERE ro_role = :role "
            ;
        } else {
            $query =
                " DELETE teacher_role "
                ." FROM teacher_role "
                ." JOIN role ON ro_id = tr_ro_id  "
                ." WHERE tr_tea_id = :id "
                ." AND ro_role = :role "
            ;
        }
        $s = $db->prepare($query);
        $s->bindValue("id",$this->id, \PDO::PARAM_INT);
        $s->bindValue("role", Role::ADMIN, \PDO::PARAM_STR);
        $s->execute();
    }

}




