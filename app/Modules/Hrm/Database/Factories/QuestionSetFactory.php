<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Models\InterviewQuestion;
use App\Modules\Hrm\Models\QuestionSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake question sets: QuestionSet::factory()->withQuestions([$communication->id => 40, $technical->id => 60])->create().
 *
 * @extends Factory<QuestionSet>
 */
class QuestionSetFactory extends Factory
{
    protected $model = QuestionSet::class;

    public function definition(): array
    {
        return ['name' => 'Set '.fake()->unique()->numberBetween(1, 99999)];
    }

    /**
     * Puts these questions in the set, in this order: question id => weight. Without ids: two new
     * questions of 50% each.
     *
     * @param  array<int, int|float>  $weights
     */
    public function withQuestions(array $weights = []): static
    {
        return $this->afterCreating(function (QuestionSet $set) use ($weights) {
            if ($weights === []) {
                $weights = InterviewQuestion::factory()->count(2)->create()->mapWithKeys(fn ($q) => [$q->id => 50])->all();
            }
            $sequence = 0;
            foreach ($weights as $questionId => $weight) {
                $set->items()->create(['interview_question_id' => $questionId, 'weight' => $weight, 'sequence' => ++$sequence]);
            }
        });
    }
}
