<?php

namespace App\Modules\Hrm\Services;

use App\Modules\Hrm\Models\JobOffer;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * The offer letter as a PDF (6.7). [REC-06] Used by the download link AND the email, so both are the same.
 *
 *     app(OfferLetter::class)->content($offer)     the PDF bytes
 *     app(OfferLetter::class)->fileName($offer)    "Offer letter OFR-0003 Rahim Uddin.pdf"
 *
 * The text lives in the view hrm::letters.offer. A company can get its own wording later by putting a
 * view with the same name in its plugin folder (Architecture: View::prependNamespace).
 */
class OfferLetter
{
    public function content(JobOffer $offer): string
    {
        $offer->loadMissing(['application.applicant', 'application.jobPosting.manpowerRequest.orgUnit', 'designation', 'grade', 'employmentType']);

        return Pdf::loadView('hrm::letters.offer', [
            'offer' => $offer,
            'applicant' => $offer->application->applicant,
            'company' => tenant()->name ?? config('app.name'),
        ])->setPaper('a4')->output();
    }

    public function fileName(JobOffer $offer): string
    {
        // Only letters, digits, spaces and dashes: safe as a download / attachment name.
        $name = preg_replace('/[^\pL\pN \-]+/u', '', $offer->application->applicant->name) ?: 'applicant';

        return __('Offer letter').' '.$offer->number().' '.$name.'.pdf';
    }
}
