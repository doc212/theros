<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;
use fpdf\FPDF;

use AppBundle\Entity\Student;
use AppBundle\Entity\Klass;
use AppBundle\Entity\Work;
use AppBundle\ResultPdf;

class PdfController extends Controller implements IAdminPage
{
    static $months = array(null, "janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août", "septembre", "octobre", "novembre", "décembre");

    private $draft;

    //batch types
    const TUTORS = "tutors";
    const GROUPED = "grouped";
    const INDIVIDUAL = "ind";
    const ALL = "all";

    //group types
    const GROUP_BY_TEACHER = "byTeacher";
    const GROUP_BY_STUDENT = "byStudent";

    /**
     * @Route("/pdf/test")
     */
    public function testAction()
    {
        $db = $this->db();
        $student = new Student($row = $db->query("
            SELECT *
            FROM student
            JOIN student_class ON sc_st_id = st_id
            JOIN class ON sc_cl_id = cl_id
            WHERE st_name like 'Pepa%'
        ")->fetch());
        $student->class = new Klass($row);

        $works = array();
        foreach ($db->query("SELECT w_id FROM work WHERE w_st_id = " . $student->id . " AND w_has_result") as $row) {
            $works[] = Work::GetFullById($db, $row["w_id"]);
        }
        $schoolyear = $this->getSchoolYear();
        $pdf = $this->createPdf();
        $this->addResults($pdf, $student, $works, $schoolyear, self::ALL);
        return $this->renderPdf($pdf);
    }

    /**
     * @Route("/pdf", name="pdf_home")
     */
    public function indexAction()
    {
        $db = $this->db();
        $schoolyear = $this->getSchoolYear();
        Work::GetCounts($db, $schoolyear, $encoded, $total, $students);
        return $this->render("pdf/index.html.twig", array(
            "encoded" => $encoded,
            "total" => $total,
            "studentCount" => $students
        ));
    }

    /**
     * @Route("/pdf/parents", name="pdf_tutors");
     */
    public function tutorsAction()
    {
        return $this->getPdf(self::TUTORS);
    }

    /**
     * @Route("/pdf/grouped-results", name="pdf_grouped");
     */
    public function groupedAction()
    {
        return $this->getPdf(self::GROUPED);
    }

    /**
     * @Route("/pdf/single-results", name="pdf_individual");
     */
    public function individualAction()
    {
        return $this->getPdf(self::INDIVIDUAL);
    }

    /**
     * @Route("/pdf/teachers", name="pdf_by_teacher")
     */
    public function teacherAction()
    {
        $this->getWorkGroups($schoolyear, $teachers, self::GROUP_BY_TEACHER);
        $pdf = $this->createPdf();
        foreach ($teachers as $t) {
            $this->addResultGroup($pdf, $t, $schoolyear, $t->works, self::GROUP_BY_TEACHER);
        }
        return $this->renderPdf($pdf);
    }

    /**
     * @Route("/pdf/all", name="pdf_all")
     */
    public function allAction()
    {
        return $this->getPdf(self::ALL);
    }

    private function getWorkGroups(&$schoolyear, &$groups, $groupBy)
    {
        $db = $this->db();
        $schoolyear = $this->getSchoolYear();
        $byTeacher = $groupBy == self::GROUP_BY_TEACHER;
        $works = Work::GetListBySchoolYear($db, $schoolyear, TRUE, $byTeacher);
        $groups = array();
        $byId = array();
        foreach ($works as $w) {
            $s = $byTeacher ? $w->teacher : $w->student;
            if (!isset($byId[$s->id])) {
                $byId[$s->id] = $s;
                $groups[] = $s;
                $s->works = array();
            } else {
                $s = $byId[$s->id];
            }
            $s->works[] = $w;
        }
    }

    private function getPdf($what)
    {
        $this->getWorkGroups($schoolyear, $students, self::GROUP_BY_STUDENT);

        $pdf = $this->createPdf();
        foreach ($students as $s) {
            $this->addResults($pdf, $s, $s->works, $schoolyear, $what);
        }
        return $this->renderPdf($pdf);
    }

    private function getLengths(&$pageWidth, &$pageHeight, &$rightMargin, &$contentWidth, &$height)
    {
        $pageWidth = ResultPdf::PAGE_WIDTH;
        $pageHeight = ResultPdf::PAGE_HEIGHT;
        $rightMargin = ResultPdf::MARGIN;
        $contentWidth = ResultPdf::CONTENT_WIDTH;
        $height = ResultPdf::LINE_HEIGHT;
    }

    private function addResults($pdf, $student, $works, $schoolyear, $what)
    {
        $this->getLengths($pageWidth, $pageHeight, $rightMargin, $contentWidth, $height);
        if ($what == self::ALL || $what == self::TUTORS) {
            $pdf->AddPage();
            $pdf->SetY(42);
            $pdf->SetX(112);
            $tutor = $student->tutor;
            $address = $student->address;
            $zip = $student->zip;
            $city = $student->city;
            $pdf->MultiCell(0, $height, utf8_decode("$tutor\n$address\n$zip - $city"));
            $date = date("j ") . utf8_decode(self::$months[date("n")]) . date(" Y");
            $pdf->SetY(75);
            $pdf->Cell(0, $height, "Schaerbeek, le $date", 0, 1, "R");
            $name = $student->name;
            $pdf->SetY(90);
            $pdf->MultiCell(0, $height, utf8_decode("Chers parents, cher élève,


    "));
            $pdf->Write($height, utf8_decode("Vous trouverez ci-joint les résultats des travaux de vacances et remise(s) à niveau de "));
            $pdf->SetFont("", "B");
            $pdf->Write($height, utf8_decode($name));
            $pdf->SetFont("", "");
            $pdf->Write($height, ".\n");
            $pdf->MultiCell(0, $height, utf8_decode("
Pour mémoire, le but du travail de vacances est de faire revoir à l'élève des aspects de la matière qui sont primordiaux pour redémarrer l'année suivante, et donc de lui assurer de meilleures bases. Les résultats des travaux de vacances et remise(s) à niveau seront reportés dans le premier bulletin afin d'examiner à chaque conseil de classe l'évolution générale de l'élève dans les branches qui étaient en échec l'année précédente.

Quant à la remise à niveau, elle a pour but de faire revoir à l'élève, par l'étude et de nouveaux exercices suivis d'un examen, des pans de la matière qui sont importants pour redémarrer l'année suivante, et donc d'assurer à votre enfant de meilleures chances de réussite. Un risque d'échec dans une branche avec remise à niveau risque d'entraîner des difficultés dans la matière concernée durant l'année. L'élève qui en juin serait toujours en échec compromettrait la réussite de son année scolaire (Voir Règlement des études).

"));
            $pdf->Write($height, utf8_decode("Le "));
            $pdf->SetFont("", "B");
            $pdf->Write($height, utf8_decode("travail de vacances"));
            $pdf->SetFont("", "");
            $pdf->Write($height, utf8_decode(" et la "));
            $pdf->SetFont("", "B");
            $pdf->Write($height, utf8_decode("remise à niveau"));
            $pdf->SetFont("", "");
            $pdf->Write($height, utf8_decode(" sont des examens "));
            $pdf->SetFont("", "B");
            $pdf->Write($height, utf8_decode("obligatoires.\n"));
            $pdf->SetFont("", "");
            $pdf->MultiCell(0, $height, utf8_decode("
Si $name est en réussite, le contrat a été rempli et nous considérons que les lacunes observées en juin ont été totalement ou partiellement levées (voir commentaire éventuel laissé par le professeur).

En cas d'échec, par contre, nous encourageons l'élève à poursuivre le travail de remédiation dans les matières concernées. En effet, vous savez que l'accumulation d'échecs dans une même discipline compromet le bon déroulement des apprentissages.


M. Payet (Directeur-Adjoint) et M. Rosi (Directeur)
"));
        }
        if ($what == self::ALL || $what == self::TUTORS || $what == self::GROUPED) {
            $this->addResultGroup($pdf, $student, $schoolyear, $works, self::GROUP_BY_STUDENT);
        }

        $this->addIndividualResults($pdf, $what, $works, $schoolyear);
    }

    private function getDraft()
    {
        if (!$this->draft) {
            $this->draft = $this->createPdf();
        }
        return $this->draft;
    }

    private function addResultGroup($pdf, $group, $schoolyear, $works, $groupBy)
    {
        $this->getLengths($pageWidth, $pageHeight, $rightMargin, $contentWidth, $height);
        $byTeacher = $groupBy == self::GROUP_BY_TEACHER;
        $groupHeader = "Fiche de résultats $schoolyear";
        if ($byTeacher) {
            $teacher = $group;
            $groupHeader .= "\nProfesseur: " . $teacher->fullname;
        } else {
            $student = $group;
            $groupHeader .= ": " . $student->name . " [" . $student->class->code . "]";
        }
        $groupHeader = utf8_decode($groupHeader);
        $totalPages = 1;
        $addPage = function($pdf) use ($height, $schoolyear, $groupHeader)
        {
            $pdf->AddPage();
            $pdf->SetFont("", "B", 16);
            $pdf->MultiCell(0, $height, $groupHeader, 0, "C");
            $pdf->Ln();
            $pdf->SetFont("", "", 12);
        };

        $addPageNumber = function($n) use (&$totalPages)
        {
            return function($pdf) use ($n, &$totalPages)
            {
                $text = "Page $n/$totalPages";
                $width = $pdf->GetStringWidth($text);
                $x = (ResultPdf::PAGE_WIDTH - $width) / 2;
                $pdf->Text($x, ResultPdf::PAGE_HEIGHT - ResultPdf::MARGIN, $text);
            };
        };
        $draft = $this->getDraft();
        $addPage($draft);
        $actions = array(
            $addPage
            , $addPageNumber($totalPages)
        );
        foreach ($works as $w) {
            $addWorkResult = function($pdf) use ($w, $byTeacher, $height, $rightMargin, $contentWidth)
            {
                $tdv = $w->isTdv();
                $type = $tdv ? "TRAVAIL DE VACANCES" : "REMISE À NIVEAU";
                $subject = $w->subject->description . " [" . $w->subject->code . "]";
                $teacherName = $w->teacher->fullname;
                if ($w->result) {
                    $result = $w->result;
                    if (!$tdv) {
                        $result .= "/100";
                    }
                    $hasResult = TRUE;
                } else {
                    $result = $tdv ? "NON-RENDU" : "ABSENT";
                    $hasResult = FALSE;
                }
                $hasRemark = $w->remark !== NULL && $w->remark != "";
                if ($hasRemark) {
                    $remark = rtrim($w->remark);
                    $linesCount = 7 + substr_count($remark, PHP_EOL) + 1;
                } else {
                    $linesCount = 5;
                }
                $top = $pdf->GetY() - 1;
                $pdf->SetFont("", "B");
                if ($byTeacher) {
                    $pdf->Write($height, utf8_decode($w->student->name . " [" . $w->student->class->code . "]"));
                    $pdf->Ln();
                    $pdf->Ln();
                }
                $pdf->Write($height, utf8_decode($type));
                $pdf->SetFont("", "");
                $pdf->Write($height, utf8_decode(" en $subject"));
                $pdf->Ln();
                $pdf->Ln();
                if (!$byTeacher) {
                    $pdf->Write($height, utf8_decode("Professeur: $teacherName"));
                    $pdf->Ln();
                    $pdf->Ln();
                }
                $pdf->Write($height, utf8_decode("Résultat: "));
                if (!$hasResult) {
                    $pdf->SetFont("", "B");
                }
                $pdf->Write($height, $result);
                $pdf->SetFont("", "");
                $pdf->Ln();
                if ($hasRemark) {
                    $pdf->Ln();
                    $comment = "Commentaire";
                    if (!$byTeacher) {
                        $comment .= " du professeur";
                    }
                    $pdf->MultiCell(0, $height, utf8_decode("$comment:\n$remark"));
                }
                $pdf->Rect($rightMargin, $top, $contentWidth, $pdf->GetY() - $top + 1);
                $bottom = $pdf->GetY() + 1;
                $pdf->Ln();
                return $bottom < $top; //new page
            };
            if ($addWorkResult($draft)) {
                $addPage($draft);
                ++$totalPages;
                $actions[] = $addPage;
                $actions[] = $addPageNumber($totalPages);
                $addWorkResult($draft);
            }
            $actions[] = $addWorkResult;
        }
        foreach ($actions as $a) {
            $a($pdf);
        }
    }

    private function addIndividualResults($pdf, $what, $works, $schoolyear)
    {
        $this->getLengths($pageWidth, $pageHeight, $rightMargin, $contentWidth, $height);
        if ($what == self::ALL || $what == self::INDIVIDUAL) {
            foreach ($works as $w) {
                $pdf->AddPage();
                $pdf->SetFont("", "B");
                $pdf->Write($height, utf8_decode("Fiche de résultat $schoolyear: " . $w->student->name . " [" . $w->student->class->code . "]"));
                $pdf->Ln();
                $tdv = $w->isTdv();
                $type = $tdv ? "Travail de vacances" : "Remise à niveau";
                $subject = $w->subject->description . " [" . $w->subject->code . "]";
                $pdf->SetFont("", "");
                $pdf->Ln();
                $pdf->Write($height, utf8_decode("$type en $subject"));
                $pdf->Ln();
                $pdf->Ln();
                $teacherName = $w->teacher->fullname;
                $pdf->Write($height, utf8_decode("Professeur: $teacherName"));
                $pdf->Ln();
                $pdf->Ln();
                $pdf->Write($height, utf8_decode("Résultat: "));
                if ($w->result) {
                    $pdf->Write($height, $w->result);
                    if (!$tdv) {
                        $pdf->Write($height, "/100");
                    }
                } else {
                    $pdf->Write($height, $tdv ? "Non rendu" : "Absent");
                }
                $pdf->Ln();
                if ($w->remark !== NULL && $w->remark != "") {
                    $pdf->Ln();
                    $pdf->Write($height, "Commentaire du professeur:");
                    $pdf->Ln();
                    $pdf->MultiCell(0, $height, utf8_decode($w->remark));
                }
            }
        }
    }

    private function renderPdf(FPDF $pdf)
    {
        $response = new Response();
        $response->headers->set("Content-Type", "application/pdf");
        $response->setContent($pdf->Output(null, "S"));
        return $response;
    }

    private function createPdf()
    {
        return new ResultPdf($this->webdir("img/logo.png"));
    }
}
