<?php
namespace Ivory\Showcase;

use Ivory\Relation\IRelation;
use Ivory\Relation\ITuple;
use Ivory\Relation\QueryRelation;

/**
 * This test presents the relation processing capabilities.
 * Moreover, it presents composing relations - on top of one relation, we build another relation.
 *
 * There is a simple model of a school scheduling system:
 * - there are lessons taught by teachers;
 * - the `lesson`:`teacher` cardinality is M:N, i.e., even multiple teachers might together teach a single lesson.
 *
 * The M:N relation is represented by the `lessonteacher` table the rows of which refer to `lesson` and `teacher`, as
 * usual. Moreover, there are two states of lesson-teacher relation:
 * - `scheduled` specifies teachers who regularly teach the lesson, i.e., reflects the normal state;
 * - `actual` specifies teachers who will actually be teaching the lesson - those may be different from the
 *   scheduled ones in case of supply teaching.
 *
 * Similarly to the two states of `lessonteacher` relation, the lesson itself has two time ranges:
 * - `scheduled_timerange`, and
 * - `actual_timerange` (this might be `NULL` meaning the lesson has been cancelled).
 */
class ProcessingTest extends \Ivory\IvoryTestCase
{
    /** @var IRelation */
    private $teachers;
    /** @var IRelation */
    private $rel;

    protected function setUp()
    {
        parent::setUp();

        $conn = $this->getIvoryConnection();
        $this->teachers = (new QueryRelation($conn,
            "VALUES
             (1, 'Angus', 'Deaton', 'Dtn'),
             (2, 'Jean', 'Tirole', 'Tir'),
             (3, 'Eugene F.', 'Fama', NULL),
             (4, 'Lars Peter', 'Hansen', NULL),
             (5, 'Robert J.', 'Shiller', NULL),
             (6, 'Ada', 'Lovelace', NULL)"
        ))->rename(['id', 'firstname', 'lastname', 'abbr']);

        // TODO: instead of copying the teacher data, compose the relations
        $this->rel = $conn->rawQuery(
            /** @lang PostgreSQL */
            "WITH lesson (id, topic, scheduled_timerange, actual_timerange) AS (
               VALUES
                 (1, '1+1',  tsrange('2015-09-01 08:00', '2015-09-01 08:45'), tsrange('2015-09-01 08:00', '2015-09-01 08:45')),
                 (2, 'Ruby', tsrange('2015-09-01 08:55', '2015-09-01 09:40'), tsrange('2015-09-01 10:00', '2015-09-01 10:45')),
                 (3, 'PHP',  tsrange('2015-09-01 08:55', '2015-09-01 09:40'), NULL),
                 (4, '1+2',  tsrange('2015-09-01 10:55', '2015-09-01 11:40'), tsrange('2015-09-01 08:55', '2015-09-01 09:40')),
                 (5, 'Perl', tsrange('2015-09-01 11:30', '2015-09-01 12:15'), tsrange('2015-09-01 11:30', '2015-09-01 12:15')),
                 (6, '1+3',  tsrange('2015-09-02 08:00', '2015-09-02 08:45'), tsrange('2015-09-02 08:00', '2015-09-02 08:45')),
                 (7, 'C',    tsrange('2015-09-02 08:55', '2015-09-02 09:40'), tsrange('2015-09-02 08:55', '2015-09-02 09:40')),
                 (8, 'C++',  tsrange('2015-09-02 10:15', '2015-09-02 11:00'), tsrange('2015-09-02 10:15', '2015-09-02 11:00'))
             ),
             teacher (id, firstname, lastname, abbr) AS (
               VALUES
                 (1, 'Angus', 'Deaton', 'Dtn'),
                 (2, 'Jean', 'Tirole', 'Tir'),
                 (3, 'Eugene F.', 'Fama', NULL),
                 (4, 'Lars Peter', 'Hansen', NULL),
                 (5, 'Robert J.', 'Shiller', NULL),
                 (6, 'Ada', 'Lovelace', NULL)
             ),
             lessonteacher (lesson_id, teacher_id, schedulingstatus) AS (
               VALUES
                 (1, 1, 'scheduled'), (1, 1, 'actual'),
                 (2, 6, 'scheduled'), (2, 6, 'actual'),
                 (3, 6, 'scheduled'), (3, 6, 'actual'),
                 (4, 2, 'scheduled'), (4, 1, 'actual'),
                 (5, 6, 'scheduled'), (5, 6, 'actual'),
                 (6, 3, 'scheduled'), (6, 3, 'actual'),
                 (6, 4, 'scheduled'), (6, 4, 'actual'),
                 (6, 5, 'scheduled'), (6, 5, 'actual'),
                 (7, 6, 'scheduled'), (7, 6, 'actual'),
                 (8, 6, 'scheduled'), (8, 6, 'actual')
             )
             SELECT l.id AS lesson_id, l.topic AS lesson_topic,
                    lt.schedulingstatus,
                    t.id AS teacher_id, t.firstname AS teacher_firstname, t.lastname AS teacher_lastname,
                    t.abbr AS teacher_abbr
             FROM lesson l
                  JOIN lessonteacher lt ON lt.lesson_id = l.id
                  JOIN teacher t ON t.id = lt.teacher_id"
        );
    }

    public function testAssocSingleLevel()
    {
        $res = $this->teachers->assoc('id', function (ITuple $t) {
            return ($t['abbr'] ?: mb_substr($t['lastname'], 0, 4));
        });

        $this->assertCount(6, $res);
        $this->assertSame('Dtn', $res[1]);
        $this->assertSame('Love', $res[6]);
    }

    public function testAssocMultiLevel()
    {
        $res = $this->rel->assoc('lesson_id', 'schedulingstatus', 'teacher_id', 'teacher_abbr');

        $this->assertSame('Tir', $res[4]['scheduled'][2]);
        $this->assertTrue(!isset($res[4]['actual'][2]));

        $this->assertCount(1, $res[4]['actual']);
        foreach ($res[4]['actual'] as $teacherId => $teacherAbbr) {
            $this->assertSame(1, $teacherId);
            $this->assertSame('Dtn', $teacherAbbr);
        }
    }

    public function testMapSingleLevel()
    {
        $res = $this->teachers->map('id');

        $this->assertCount(6, $res);
        $this->assertSame('Ada', $res[6]['firstname']);
        $this->assertSame(
            'Ada Lovelace',
            $res[6]->value(function (ITuple $t) {
                return "$t[firstname] $t[lastname]";
            })
        );
    }

    public function testMapMultiLevel()
    {
        $res = $this->rel->map('teacher_id', 'schedulingstatus', 'lesson_id');

        $this->assertSame('Ruby', $res[6]['actual'][2]['lesson_topic']);
        $this->assertCount(2, $res[1]['actual']);
        $this->assertCount(1, $res[1]['scheduled']);
        $this->assertCount(1, $res[2]['scheduled']);
        $this->assertTrue(!isset($res[2]['actual']));
    }

    public function testMultimapSingleLevel()
    {
//        $res = $this->teachers->multimap(function (ITuple $t) { return $t['firstname'][0]; });
//
//        $this->assertInstanceOf(IRelation::class, $res['A']);
//        $this->assertCount(2, $res['A']);
//        /** @var IRelation $jRel */
//        $jRel = $res['J'];
//        $this->assertEquals(
//            [['id' => 2, 'firstname' => 'Jean', 'lastname' => 'Tirole', 'abbr' => 'Tir']],
//            $jRel->toArray()
//        );
    }

    public function testMultimapMultiLevel()
    {
//        $res = $this->rel->multimap(); // TODO
    }
}
