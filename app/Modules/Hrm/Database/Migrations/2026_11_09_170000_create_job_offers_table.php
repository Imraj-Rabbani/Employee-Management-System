<?php

use App\Modules\Hrm\Models\JobOffer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Job offers (6.7): what the company offers an applicant, approved through a workflow, sent as a letter,
 * then accepted or declined. [REC-06]
 *
 *   gross_salary          monthly gross offered; compared with the grade band, the request's range and
 *                         the applicant's expected salary (JobOffer::comparisons())
 *   grade / designation / employment type   prefilled from the manpower request, may differ
 *   approved_at           set by onApproved(); the application then moves to "Offered"
 *   letter_sent_at        the offer letter (PDF) was emailed to the applicant
 *   response              'accepted' / 'declined' (OfferResponse) + responded_on; declined → the
 *                         application is withdrawn with the chosen reason
 *
 * No status column: JobOffer::status() works it out from these columns and the approval rows.
 * Also registers the approval type "job_offer" (Workflow → Setup).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->decimal('gross_salary', 14, 2);
            $table->foreignId('grade_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('designation_id')->constrained()->restrictOnDelete();
            $table->foreignId('employment_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('joining_date');
            $table->date('expires_on')->nullable();      // answer by this date
            $table->text('terms')->nullable();           // extra conditions printed in the letter
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('letter_sent_at')->nullable();
            $table->string('response', 20)->nullable();
            $table->date('responded_on')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $table->timestamps();
        });

        if (! DB::table('approval_types')->where('key', 'job_offer')->exists()) {
            DB::table('approval_types')->insert([
                'key' => 'job_offer',
                'name' => 'Job offer',
                'model_class' => JobOffer::class,
                'is_chained' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('approval_types')->where('key', 'job_offer')->delete();
        Schema::dropIfExists('job_offers');
    }
};
