<?php

namespace Tests\Concerns;

use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\InterviewStage;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Models\QuestionSet;

/**
 * An interview stage for tests: $this->stageFor($application) = "Stage 1" of its posting
 * (made once, with a 2-question set unless $set is given).
 */
trait MakesInterviewStages
{
    protected function stageFor(Application|JobPosting $for, string $name = 'Stage 1', ?QuestionSet $set = null): InterviewStage
    {
        $postingId = $for instanceof Application ? $for->job_posting_id : $for->id;

        return InterviewStage::firstOrCreate(
            ['job_posting_id' => $postingId, 'name' => $name],
            ['question_set_id' => ($set ?? QuestionSet::factory()->withQuestions()->create())->id],
        );
    }
}
