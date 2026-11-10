<?php

namespace Tests\Feature\Hrm;

use App\Modules\Hrm\Enums\ApplicationStage;
use Tests\TestCase;

/** Task 6.1: the stages of a job application. */
class ApplicationStageTest extends TestCase
{
    public function test_stages_in_pipeline_order(): void
    {
        $this->assertSame(
            ['applied', 'shortlisted', 'interview', 'offered', 'hired', 'rejected', 'withdrawn'],
            ApplicationStage::values(),
        );
    }

    public function test_hired_rejected_and_withdrawn_close_the_application(): void
    {
        $final = array_filter(ApplicationStage::cases(), fn (ApplicationStage $stage) => $stage->isFinal());

        $this->assertSame([ApplicationStage::Hired, ApplicationStage::Rejected, ApplicationStage::Withdrawn], array_values($final));
    }

    public function test_only_rejected_and_withdrawn_need_a_reason(): void
    {
        $this->assertTrue(ApplicationStage::Rejected->needsReason());
        $this->assertTrue(ApplicationStage::Withdrawn->needsReason());
        $this->assertFalse(ApplicationStage::Hired->needsReason());
    }

    public function test_labels_follow_the_language(): void
    {
        app()->setLocale('bn');
        $this->assertSame('নিয়োগপ্রাপ্ত', ApplicationStage::Hired->label());
        app()->setLocale('en');

        $this->assertSame('Shortlisted', ApplicationStage::labelFor('shortlisted'));
    }
}
