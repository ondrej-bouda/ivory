<?php
declare(strict_types=1);
namespace Ivory\Showcase;

use Ivory\Data\Map\IRelationMap;
use Ivory\IvoryTestCase;
use Ivory\Query\SqlRelationDefinition;
use Ivory\Relation\IRelation;
use Ivory\Relation\ITuple;

/**
 * This test presents the relation processing capabilities.
 * Moreover, it presents composing relations - on top of one relation, we build another relation.
 *
 * There is a simple model of a school scheduling system:
 * - there are lessons taught by teachers;
 * - the `lesson`:`teacher` cardinality is M:N, i.e., even multiple teachers might together teach a single lesson.
 *
 * The M:N relation is represented by the `lessonteacher` table, the rows of which refer to `lesson` and `teacher`, as
 * usual. Moreover, there are two states of lesson-teacher relation:
 * - `scheduled` specifies teachers who regularly teach the lesson, i.e., reflects the normal state;
 * - `actual` specifies teachers who will actually be teaching the lesson - those may be different from the scheduled
 *   ones in case of supply teaching.
 *
 * Similarly to the two states of `lessonteacher` relation, the lesson itself has two time ranges:
 * - `scheduled_timerange`, and
 * - `actual_timerange` (this might be `NULL` meaning the lesson has been cancelled).
 */
class ProcessingTest extends IvoryTestCase
{
    /** @var IRelation */
    private $teachers;
    /** @var IRelation */
    private $rel;

    /**
     * Set up the sample data. Note that Ivory can build the definitions for relations on top of each other, as
     * demonstrated in the WITH table expression "teacher".
     */
    protected function setUp(): void
    {
        parent::setUp();

        $conn = $this->getIvoryConnection();

        $teachersRelDef = SqlRelationDefinition::fromSql(
            "VALUES
             (1, 'Angus', 'Deaton', 'Dtn'),
             (2, 'Jean', 'Tirole', 'Tir'),
             (3, 'Eugene F.', 'Fama', NULL),
             (4, 'Lars Peter', 'Hansen', NULL),
             (5, 'Robert J.', 'Shiller', NULL),
             (6, 'Ada', 'Lovelace', NULL)"
        );

        $this->teachers = $conn->query($teachersRelDef)
            ->rename(['id', 'firstname', 'lastname', 'abbr']);

        $this->rel = $conn->query(
            "WITH lesson (id, topic, scheduled_timerange, actual_timerange) AS (
               VALUES
                 (
                   1, '1+1',
                   tsrange('2015-09-01 08:00', '2015-09-01 08:45'), tsrange('2015-09-01 08:00', '2015-09-01 08:45')
                 ),
                 (
                   2, 'Ruby',
                   tsrange('2015-09-01 08:55', '2015-09-01 09:40'), tsrange('2015-09-01 10:00', '2015-09-01 10:45')
                 ),
                 (
                   3, 'PHP',
                   tsrange('2015-09-01 08:55', '2015-09-01 09:40'), NULL
                 ),
                 (
                   4, '1+2',
                   tsrange('2015-09-01 10:55', '2015-09-01 11:40'), tsrange('2015-09-01 08:55', '2015-09-01 09:40')
                 ),
                 (
                   5, 'Perl',
                   tsrange('2015-09-01 11:30', '2015-09-01 12:15'), tsrange('2015-09-01 11:30', '2015-09-01 12:15')
                 ),
                 (
                   6, '1+3',
                   tsrange('2015-09-02 08:00', '2015-09-02 08:45'), tsrange('2015-09-02 08:00', '2015-09-02 08:45')
                 ),
                 (
                   7, 'C',
                   tsrange('2015-09-02 08:55', '2015-09-02 09:40'), tsrange('2015-09-02 08:55', '2015-09-02 09:40')
                 ),
                 (
                   8, 'C++',
                   tsrange('2015-09-02 10:15', '2015-09-02 11:00'), tsrange('2015-09-02 10:15', '2015-09-02 11:00'
                 ))
             ),
             teacher (id, firstname, lastname, abbr) AS (
               %rel:teachersRel
             ),
             lessonteacher (lesson_id, teacher_id, scheduling_status) AS (
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
                    lt.scheduling_status,
                    t.id AS teacher_id, t.firstname AS teacher_firstname, t.lastname AS teacher_lastname,
                    t.abbr AS teacher_abbr
             FROM lesson l
                  JOIN lessonteacher lt ON lt.lesson_id = l.id
                  JOIN teacher t ON t.id = lt.teacher_id
             ORDER BY l.id, lt.scheduling_status DESC, t.id",
            [
                'teachersRel' => $teachersRelDef,
            ]
        );
    }

    public function testCount()
    {
        self::assertCount(6, $this->teachers);
    }

    public function testTraversal()
    {
        $exp = ['Dtn', 'Tir', null, null, null, null];
        foreach ($this->teachers as $i => $tuple) {
            assert($tuple instanceof ITuple);
            self::assertSame($exp[$i], $tuple->abbr, "Tuple $i does not match");
        }
    }

    public function testValue()
    {
        self::assertSame(1, $this->teachers->value());
        self::assertSame('Angus', $this->teachers->value('firstname'));
        self::assertSame('Angus', $this->teachers->value(1));
        self::assertSame('Ada', $this->teachers->value(1, 5));
        self::assertSame('Ada Lovelace',
            $this->teachers->value(function (ITuple $t) { return "{$t->firstname} {$t->lastname}"; }, 5)
        );
    }

    /**
     * Get one tuple at a time from the relation.
     */
    public function testTuple()
    {
        self::assertSame(1, $this->teachers->tuple()->id);
        self::assertSame('Ada', $this->teachers->tuple(5)->value('firstname'));
        self::assertSame('Ada Lovelace',
            $this->teachers->tuple(5)->value(function (ITuple $t) { return "{$t->firstname} {$t->lastname}"; })
        );

        self::assertSame(
            ['id' => 6, 'firstname' => 'Ada', 'lastname' => 'Lovelace', 'abbr' => null],
            $this->teachers->tuple(5)->toMap()
        );
        self::assertSame(
            [6, 'Ada', 'Lovelace', null],
            $this->teachers->tuple(5)->toList()
        );
    }

    /**
     * Sometimes, the query leads to exactly one tuple, e.g., when selecting a maximum or calling a scalar function.
     * Let Ivory check there actually is at most one row, and express clearly by the code there always is one row only.
     *
     * Also note we are sending the relation (i.e., a fetched set of rows) back to PostgreSQL as a base for the query.
     * This will usually come in handy in more complicated situations, such as when PostgreSQL is given a too complex
     * query while querying parts of it result in a better query plan.
     */
    public function testQueryOneTuple()
    {
        $conn = $this->getIvoryConnection();
        $tuple = $conn->querySingleTuple(
            'SELECT MIN(lastname), MAX(lastname) FROM (%rel) t',
            $this->teachers
        );
        self::assertSame(['Deaton', 'Tirole'], $tuple->toList());
    }

    public function testCol()
    {
        $firstNameCol = $this->teachers->col('firstname');

        self::assertSame('Angus', $firstNameCol->value(0));
        self::assertSame(
            ['Angus', 'Jean', 'Eugene F.', 'Lars Peter', 'Robert J.', 'Ada'],
            $firstNameCol->toArray()
        );

        $abbrCol = $this->teachers->col(function (ITuple $t) {
            return ($t->abbr ? : mb_substr($t->lastname, 0, 4));
        });
        $expected = ['Dtn', 'Tir', 'Fama', 'Hans', 'Shil', 'Love'];
        foreach ($abbrCol as $i => $v) {
            self::assertSame($expected[$i], $v, "Columns value at offset $i");
        }
    }

    public function testFilter()
    {
        $res = $this->teachers->filter(function (ITuple $t) {
            return ($t->firstname[0] == 'A');
        });

        self::assertCount(2, $res);
        $exp = ['Angus', 'Ada'];
        foreach ($res as $i => $tuple) {
            assert($tuple instanceof ITuple);
            self::assertSame($exp[$i], $tuple->value('firstname'), "Tuple $i does not match");
        }
    }

    public function testProject()
    {
        $res = $this->teachers->project([
            'id',
            'initials' => function (ITuple $t) { return "{$t->firstname[0]}.{$t->lastname[0]}."; },
        ]);

        self::assertSame(
            ['id' => 1, 'initials' => 'A.D.'],
            $res->tuple(0)->toMap()
        );
    }

    public function testProjectSimpleMacro()
    {
        $res = $this->rel->project(['lesson_id', 'stat' => 'scheduling_status', '*' => 'teacher_*']);

        self::assertSame(
            [
                'lesson_id' => 1,
                'stat' => 'scheduled',
                'id' => 1,
                'firstname' => 'Angus',
                'lastname' => 'Deaton',
                'abbr' => 'Dtn',
            ],
            $res->tuple(0)->toMap()
        );
    }

    public function testProjectPcreMacro()
    {
        $res = $this->rel->project(['\\1' => '/^(.+)_id$/', 'scheduling_status']);

        self::assertSame(
            ['lesson' => 1, 'teacher' => 1, 'scheduling_status' => 'scheduled'],
            $res->tuple(0)->toMap()
        );
    }

    public function testAssocSingleLevel()
    {
        $res = $this->teachers->assoc('id', function (ITuple $t) {
            return ($t->abbr ? : mb_substr($t->lastname, 0, 4));
        });

        self::assertCount(6, $res);
        self::assertSame('Dtn', $res[1]);
        self::assertSame('Love', $res[6]);
    }

    public function testAssocMultiLevel()
    {
        $res = $this->rel->assoc('lesson_id', 'scheduling_status', 'teacher_id', 'teacher_abbr');

        self::assertSame('Tir', $res[4]['scheduled'][2]);
        self::assertTrue(!isset($res[4]['actual'][2]));

        self::assertCount(1, $res[4]['actual']);
        foreach ($res[4]['actual'] as $teacherId => $teacherAbbr) {
            self::assertSame(1, $teacherId);
            self::assertSame('Dtn', $teacherAbbr);
        }
    }

    public function testMapSingleLevel()
    {
        $res = $this->teachers->map('id');

        self::assertCount(6, $res);
        self::assertSame('Ada', $res[6]->firstname);
        self::assertSame(
            'Ada Lovelace',
            $res[6]->value(function (ITuple $t) {
                return "{$t->firstname} {$t->lastname}";
            })
        );
    }

    public function testMapMultiLevel()
    {
        $res = $this->rel->map('teacher_id', 'scheduling_status', 'lesson_id');

        self::assertSame('Ruby', $res[6]['actual'][2]->lesson_topic);
        self::assertCount(2, $res[1]['actual']);
        self::assertCount(1, $res[1]['scheduled']);
        self::assertCount(1, $res[2]['scheduled']);
        self::assertTrue(!isset($res[2]['actual']));
    }

    public function testMultimapSingleLevel()
    {
        $res = $this->teachers->multimap(function (ITuple $t) { return $t->firstname[0]; });

        self::assertInstanceOf(IRelation::class, $res['A']);
        self::assertCount(2, $res['A']);
        $jRel = $res['J'];
        assert($jRel instanceof IRelation);
        self::assertEquals(
            [
                ['id' => 2, 'firstname' => 'Jean', 'lastname' => 'Tirole', 'abbr' => 'Tir'],
            ],
            $jRel->toArray()
        );
    }

    public function testMultimapMultiLevel()
    {
        $res = $this->rel->multimap('teacher_id', 'scheduling_status');
        self::assertInstanceOf(IRelationMap::class, $res);
        self::assertInstanceOf(IRelationMap::class, $res[1]);
        self::assertInstanceOf(IRelation::class, $res[1]['actual']);

        $deatonActualLessonRel = $res[1]['actual'];
        assert($deatonActualLessonRel instanceof IRelation);
        self::assertInstanceOf(IRelation::class, $deatonActualLessonRel);
        self::assertSame('1+1', $deatonActualLessonRel->value('lesson_topic'));
        self::assertSame(['1+1', '1+2'], $deatonActualLessonRel->col('lesson_topic')->toArray());

        // Each multimap item is a relation, and as such offers IRelation::col() and other standard relation methods.
        $expectedMap = [
            1 => [ // teacher ID
                'actual' => ['1+1', '1+2'], // under each scheduling status, we list the lesson topics
                'scheduled' => ['1+1'],
            ],
            2 => [
                'actual' => [],
                'scheduled' => ['1+2'],
            ],
            3 => [
                'actual' => ['1+3'],
                'scheduled' => ['1+3'],
            ],
            4 => [
                'actual' => ['1+3'],
                'scheduled' => ['1+3'],
            ],
            5 => [
                'actual' => ['1+3'],
                'scheduled' => ['1+3'],
            ],
            6 => [
                'actual' => ['Ruby', 'PHP', 'Perl', 'C', 'C++'],
                'scheduled' => ['Ruby', 'PHP', 'Perl', 'C', 'C++'],
            ],
        ];
        foreach ($res as $teacherId => $lessonsByStatus) {
            foreach ($lessonsByStatus as $status => $lessons) {
                assert($lessons instanceof IRelation);
                $lessonTopics = $lessons->col('lesson_topic');
                self::assertSame($expectedMap[$teacherId][$status], $lessonTopics->toArray());
            }
        }
    }

    public function testToSet()
    {
        $idSet = $this->teachers->toSet('id');

        self::assertTrue($idSet->contains(6));
        self::assertFalse($idSet->contains(7));

        $abbrSet = $this->teachers->toSet(function (ITuple $t) {
            return ($t->abbr ? : mb_substr($t->lastname, 0, 4));
        });

        self::assertTrue($abbrSet->contains('Dtn'));
        self::assertTrue($abbrSet->contains('Love'));
        self::assertFalse($abbrSet->contains('Ada'));
        self::assertFalse($abbrSet->contains(null));
    }

    public function testComposition()
    {
        $res = $this->teachers
            ->project(['id', 'firstname'])
            ->filter(function (ITuple $t) { return ($t->firstname[0] == 'A'); })
            ->rename(['firstname' => 'name'])
            ->assoc('name', 'id');

        self::assertCount(2, $res);
        self::assertSame(1, $res['Angus']);
        self::assertSame(6, $res['Ada']);
    }
}
