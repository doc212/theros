<?php

namespace AppBundle\Entity;

use Doctrine\DBAL\Driver\Connection as Db;

class Work
{
    public $id;
    public $type;
    public $student;
    public $subject;
    public $schoolyear;
    public $teacher;
    public $description;
    public $remark;
    public $result;
    public $hasResult;

    public function __construct($row)
    {
        $this->id = $row["w_id"];
        $this->type= $row["w_type"] == 1?"TDV":"RAN";
        $this->student = $row["w_st_id"];
        $this->subject = $row["w_sub_id"];
        $this->schoolyear = $row["w_sy_id"];
        $this->teacher = $row["w_tea_id"];
        $this->description = $row["w_description"];
        $this->remark = $row["w_remark"];
        $this->result = $row["w_result"];
        $this->hasResult = (bool)$row["w_has_result"];
    }

    public static function GetFull($row, $withClass=FALSE)
    {
        $w=new Work($row);
        $w->student = new Student($row);
        $w->subject = new Subject($row);
        $w->schoolyear = new SchoolYear($row);
        $w->teacher = Teacher::FromRow($row);
        if ($withClass) {
            $w->student->class = new Klass($row);
        }
        return $w;
    }

    /**
     * Return an full instance of work whose properties are objects
     * by its id.
     * The student->class property is also set.
     */
    public static function GetFullById(Db $db, $id)
    {
        $s=$db->prepare("
            SELECT *
            FROM work
            JOIN schoolyear ON sy_id = w_sy_id
            JOIN student ON st_id = w_st_id
            JOIN student_class ON sc_st_id = st_id AND sc_sy_id = sy_id
            JOIN class ON cl_id = sc_cl_id
            JOIN subject ON sub_id = w_sub_id
            LEFT JOIN teacher ON tea_id = w_tea_id
            WHERE w_id = :id
        ");
        $s->bindValue("id", $id, \PDO::PARAM_INT);
        $s->execute();
        $row=$s->fetch();
        if($row){
            $w=self::GetFull($row, TRUE);
            return $w;
        } else {
            return NULL;
        }
    }

    public function update(Db $db)
    {
        $s = $db->prepare("
            UPDATE work SET
                w_type = :type
                , w_sub_id = :subjectId
                , w_description = :description
                , w_tea_id = :teacherId
                , w_result = :result
                , w_has_result = :hasResult
                , w_st_id = :studentId
                , w_remark = :remark
            WHERE w_id = :id
        ");
        $s->bindValue("type", $this->type, \PDO::PARAM_INT);
        $s->bindValue("subjectId", is_object($this->subject)? $this->subject->id : $this->subject, \PDO::PARAM_INT);
        $s->bindValue("description", $this->description, \PDO::PARAM_STR);
        $s->bindValue("teacherId", is_object($this->teacher)? $this->teacher->id : $this->teacher, \PDO::PARAM_INT);
        $s->bindValue("studentId", is_object($this->student)? $this->student->id : $this->student, \PDO::PARAM_INT);
        $s->bindValue("result", $this->result, \PDO::PARAM_STR);
        $s->bindValue("hasResult", $this->hasResult, \PDO::PARAM_BOOL);
        $s->bindValue("remark", $this->remark, \PDO::PARAM_STR);
        $s->bindValue("id", $this->id, \PDO::PARAM_INT);
        $s->execute();
    }

    /**
     * Retrieve a list of all Work instance associated to the given schoolyear (desc)
     */
    public static function GetListBySchoolYear(Db $db, $schoolyear, $onlyWithResult = FALSE, $orderByTeacher = FALSE)
    {
        $query =
            " SELECT * "
            ." FROM work "
            ." JOIN schoolyear ON sy_id = w_sy_id "
            ." JOIN student ON st_id = w_st_id "
            ." JOIN student_class ON sc_st_id = st_id AND sc_sy_id = sy_id "
            ." JOIN class ON cl_id = sc_cl_id "
            ." JOIN subject ON sub_id = w_sub_id "
            ." LEFT JOIN teacher ON tea_id = w_tea_id "
            ." WHERE sy_desc = :schoolyear "
        ;
        if ($onlyWithResult) {
            $query .= " AND w_has_result ";
        }
        if ($orderByTeacher) {
            $query .= " ORDER BY tea_fullname, st_name, sub_code ";
        } else {
        $query .= " ORDER BY cl_desc, st_name, sub_code";
        }
        $s = $db->prepare($query);
        $s->bindValue("schoolyear", $schoolyear, \PDO::PARAM_STR);
        $s->execute();
        $result = $s->fetchAll();
        $list=array();
        foreach ($result as $row) {
            $list[] = self::GetFull($row, TRUE);
        }
        return $list;
    }

    /**
     * Return whether this work is a TDV
     */
    public function isTdv()
    {
        return $this->type == "TDV";
    }

    /**
     * Get the number of encoded results and the total number of works for a given schoolyear
     */
    public static function GetCounts(Db $db, $schoolyear, &$encoded, &$total, &$students = NULL)
    {
        $query =
            " SELECT SUM(w_has_result = 1) AS encoded, COUNT(*) AS total, COUNT( DISTINCT w_st_id ) AS students "
            ." FROM work "
            ." JOIN schoolyear ON sy_id = w_sy_id "
            ." WHERE sy_desc = :schoolyear "
        ;
        $s = $db->prepare($query);
        $s->bindValue("schoolyear", $schoolyear, \PDO::PARAM_STR);
        $s->execute();
        $result = $s->fetch();
        $encoded = $result["encoded"];
        $total = $result["total"];
        $students = $result["students"];
    }
}
