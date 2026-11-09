{{--
    Offer letter (6.7), rendered to PDF by Hrm\Services\OfferLetter. $offer, $applicant, $company.
    Plain HTML + inline CSS: dompdf does not run Tailwind. DejaVu Sans prints Bangla names and "৳".
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 48px 56px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; line-height: 1.55; }
        .company { font-size: 18px; font-weight: bold; color: #1e2a5a; }
        .muted { color: #6b7280; }
        .ref { margin-top: 28px; }
        h1 { font-size: 15px; margin: 24px 0 12px; }
        table { width: 100%; border-collapse: collapse; margin: 12px 0 16px; }
        td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        td.label { width: 38%; color: #6b7280; }
        .terms { white-space: pre-line; }
        .sign { margin-top: 48px; }
        .line { border-top: 1px solid #9ca3af; width: 200px; margin-top: 40px; padding-top: 4px; }
        .accept { margin-top: 40px; padding-top: 12px; border-top: 1px dashed #9ca3af; }
    </style>
</head>
<body>
    <div class="company">{{ $company }}</div>

    <div class="ref">
        <div class="muted">{{ __('Ref') }}: {{ $offer->number() }} · {{ format_date(now()) }}</div>
        <div style="margin-top: 12px;">
            <strong>{{ $applicant->name }}</strong><br>
            @if ($applicant->address) {{ $applicant->address }}<br> @endif
            @if ($applicant->email) {{ $applicant->email }} @endif
        </div>
    </div>

    <h1>{{ __('Offer of employment: :designation', ['designation' => $offer->designation->name]) }}</h1>

    <p>{{ __('Dear :name,', ['name' => $applicant->first_name]) }}</p>
    <p>{{ __('Following your interview, we are pleased to offer you the position below at :company.', ['company' => $company]) }}</p>

    <table>
        <tr><td class="label">{{ __('Designation') }}</td><td>{{ $offer->designation->name }}</td></tr>
        <tr><td class="label">{{ __('Department') }}</td><td>{{ $offer->application->jobPosting->manpowerRequest->orgUnit?->name }}</td></tr>
        @if ($offer->grade)
            <tr><td class="label">{{ __('Grade') }}</td><td>{{ $offer->grade->name }}</td></tr>
        @endif
        @if ($offer->employmentType)
            <tr><td class="label">{{ __('Employment type') }}</td><td>{{ $offer->employmentType->name }}</td></tr>
        @endif
        <tr><td class="label">{{ __('Gross salary (monthly)') }}</td><td><strong>{{ \App\Modules\Core\Models\Currency::defaultCode() }} {{ number_format((float) $offer->gross_salary, 2) }}</strong></td></tr>
        <tr><td class="label">{{ __('Joining date') }}</td><td>{{ format_date($offer->joining_date) }}</td></tr>
    </table>

    @if ($offer->terms)
        <p><strong>{{ __('Terms') }}</strong></p>
        <p class="terms">{{ $offer->terms }}</p>
    @endif

    <p>
        @if ($offer->expires_on)
            {{ __('Please confirm your acceptance by :date. After that date this offer is no longer valid.', ['date' => format_date($offer->expires_on)]) }}
        @else
            {{ __('Please confirm your acceptance at your earliest convenience.') }}
        @endif
    </p>

    <div class="sign">
        <p>{{ __('Yours sincerely,') }}</p>
        <div class="line">{{ __('For :company', ['company' => $company]) }}</div>
    </div>

    <div class="accept">
        <strong>{{ __('Acceptance') }}</strong><br>
        {{ __('I accept this offer on the terms above.') }}
        <div class="line">{{ $applicant->name }} · {{ __('Date') }}</div>
    </div>
</body>
</html>
