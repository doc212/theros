<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use AppBundle\Entity\RawData;
use AppBundle\Entity\Klass;
use AppBundle\Entity\Student;
use AppBundle\Entity\Subject;
use AppBundle\Entity\Teaching;
use AppBundle\Entity\Work;

/**
 * @Route("/rawdata")
 */
class RawDataController extends Controller implements IAdminPage
{
    /**
     * @Route("/", name="rawdata_home")
     */
    public function indexAction(Request $request)
    {
        $db=$this->get("database_connection");
        $schoolyear = $this->getParameter("schoolyear");
        $stmt = $db->prepare("
            SELECT *
            FROM raw_data
            JOIN student ON st_id = rd_st_id
            JOIN class ON cl_id = rd_cl_id
            JOIN schoolyear ON rd_sy_id = sy_id
            WHERE sy_desc = :sy_desc
            ORDER BY cl_desc, st_name
        ");
        $stmt->bindValue("sy_desc", $schoolyear, \PDO::PARAM_STR);
        $stmt->execute();
        $res = $stmt->fetchAll();
        $works=[];
        foreach($res as $row){
            $w=RawData::GetFull($row);
            $works[] = $w;
        }

        // replace this example code with whatever you need
        return $this->render('rawdata/index.html.twig', array(
            "works"=>$works,
            'base_dir' => realpath($this->container->getParameter('kernel.root_dir').'/..'),
        ));
    }

    /**
     * @Route("/details/{id}", name="rawdata_details", requirements={"id":"\d+"})
     */
    public function detailsAction($id)
    {
        $db=$this->get("database_connection");
        //get requested raw data
        $stmt=$db->prepare("
            SELECT *
            FROM raw_data
            JOIN student ON st_id = rd_st_id
            JOIN class ON cl_id = rd_cl_id
            WHERE rd_id = :id
        ");
        $stmt->bindValue("id",$id, \PDO::PARAM_INT);
        $stmt->execute();
        $result=$stmt->fetch();
        $raw = RawData::GetFull($result);

        //get list of current works
        $s=$db->prepare("
            SELECT *
            FROM work
            JOIN raw_data_work ON rdw_w_id = w_id
            JOIN subject ON w_sub_id = sub_id
            JOIN schoolyear ON w_sy_id = sy_id
            LEFT JOIN teacher ON tea_id = w_tea_id
            JOIN student ON st_id = w_st_id
            WHERE rdw_rd_id = :id
        ");
        $s->bindValue("id",$id, \PDO::PARAM_INT);
        $s->execute();
        $result = $s->fetchAll();
        $works=[];
        foreach($result as $row){
            $works[]=Work::GetFull($row);
        }

        //get list of subjects
        $s=$db->prepare("
            SELECT DISTINCT s.*
            FROM teacher_subject
            JOIN subject s ON ts_sub_id = sub_id
            JOIN schoolyear sy ON ts_sy_id = sy_id
            WHERE ts_cl_id = :cl_id
            AND sy_desc = :schoolyear
            ORDER BY sub_code, sub_desc
        ");
        $schoolyear = $this->getParameter("schoolyear");
        $s->bindValue("cl_id", $raw->class->id, \PDO::PARAM_INT);
        $s->bindValue("schoolyear", $schoolyear, \PDO::PARAM_STR);
        $s->execute();
        $result=$s->fetchAll();
        $subjects=[];
        foreach($result as $row){
            $subjects[] = new Subject($row);
        }

        return $this->render("rawdata/details.html.twig", array(
            "raw"=>$raw,
            "subjects"=>$subjects,
            "works"=>$works,
        ));
    }

    /**
     * @Route("/add", name="rawdata_add")
     */
    public function addAction(Request $request)
    {
        $studentId = $request->request->get("studentId");
        $subjectId = $request->request->get("subjectId");
        $rawDataId = $request->request->get("rawDataId");
        $description = $request->request->get("description");
        $type = $request->request->get("type");

        $db=$this->get("database_connection");

        $db->beginTransaction();
        try
        {
            $s=$db->prepare("
                INSERT INTO work(w_type, w_sub_id, w_st_id, w_description, w_sy_id)
                SELECT :type, :subjectId, :studentId, :description, sy_id
                FROM schoolyear
                WHERE sy_desc = :schoolyear
            ");
            $s->bindValue("type", $type, \PDO::PARAM_INT);
            $s->bindValue("subjectId", $subjectId, \PDO::PARAM_INT);
            $s->bindValue("studentId", $studentId, \PDO::PARAM_INT);
            $s->bindValue("description", $description, \PDO::PARAM_STR);
            $s->bindValue("schoolyear", $this->getParameter("schoolyear"), \PDO::PARAM_STR);
            $s->execute();

            $workId = $db->lastInsertId();
            $s=$db->prepare("INSERT INTO raw_data_work(rdw_rd_id, rdw_w_id) VALUES (:rawDataId, :workId)");
            $s->bindValue("rawDataId", $rawDataId, \PDO::PARAM_INT);
            $s->bindValue("workId", $workId, \PDO::PARAM_INT);
            $s->execute();

            $db->commit();
        }
        catch(\Exception $e)
        {
            $db->rollBack();
            throw $e;
        }
        return $this->redirectToRoute("rawdata_details", array("id"=>$rawDataId));
    }

    /**
     * @Route("/delete/{id}/{rawDataId}", name="rawdata_delete", requirements={"id":"\d+", "rawDataId":"\d+"})
     * @Method({"POST"})
     */
    public function deleteAction($id, $rawDataId)
    {
        $db=$this->get("database_connection");
        $s=$db->prepare("DELETE FROM work WHERE w_id = :id");
        $s->bindValue("id", $id, \PDO::PARAM_INT);
        $s->execute();
        return $this->redirectToRoute("rawdata_details", array("id"=>$rawDataId));
    }

    /**
     * @Route("/treat/{id}/{treated}", name="rawdata_treat", requirements={"id":"\d+", "treated":"(0|1)"})
     * @Method({"POST"})
     */
    public function treatAction($id, $treated)
    {
        $treated=!$treated;
        $db=$this->get("database_connection");
        $s=$db->prepare("UPDATE raw_data SET rd_treated = :treated WHERE rd_id = :id");
        $s->bindValue("id", $id, \PDO::PARAM_INT);
        $s->bindValue("treated", $treated, \PDO::PARAM_BOOL);
        $s->execute();

        if($treated){
            $result=$db->query("
                SELECT rd_id
                FROM raw_data
                JOIN student ON rd_st_id = st_id
                JOIN class ON cl_id = rd_cl_id
                WHERE NOT rd_treated
                ORDER BY cl_desc, st_name
                LIMIT 1
            ")->fetch();
            if($result){
                return $this->redirectToRoute("rawdata_details", array("id"=>$result["rd_id"]));
            }
        }
        return $this->redirectToRoute("rawdata_home");
    }
}
