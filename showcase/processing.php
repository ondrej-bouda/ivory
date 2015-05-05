<?php
namespace Ppg\Showcase;

// TODO: show how the relations might be processed; rewrite the following Edookit code excerpt:
$model = new Model_vw_lesson_available_person();
$rows = $model->fetchJoin(
	'JOIN person ON person.id = person_id',
	[Model::NOT_ALL_FIELDS, 'lesson_id', 'person_id', 'person.lastname', 'person.firstname'],
	[
		sprintf('lesson_id IN (%s)', implode(',', $lessonIds)),
		sprintf('person_id IN (%s)', implode(',', $teacherIds)) // FIXME: this is inaccurate; only those persons should be considered who are enrolled as employees for the whole duration of the specific lesson
	],
	'lesson_id, person.lastname, person.firstname'
);
return ArrayUtils::groupBy($rows, 'lesson_id', function ($group) {
	return ArrayUtils::pairProjection($group, 'person_id', function ($row) {
		return Model_person::getScheduleIdentifier($row);
	});
});
