<?php

namespace App\Modules\Hrm\Enums;

use App\Core\Enums\HasOptions;

/**
 * Kind of an employee document (5.5). New in the rebuild (legacy had free text), so the numbers are ours.
 * "Other" uses the document's title, e.g. "Gun licence".
 */
enum DocumentType: int
{
    use HasOptions;

    case NationalId = 1;
    case Passport = 2;
    case DrivingLicence = 3;
    case BirthCertificate = 4;
    case TinCertificate = 5;
    case WorkPermit = 6;
    case Visa = 7;
    case MedicalCertificate = 8;
    case PoliceClearance = 9;
    case AppointmentLetter = 10;
    case Other = 99;

    public function label(): string
    {
        return match ($this) {
            self::NationalId => __('National ID'),
            self::Passport => __('Passport'),
            self::DrivingLicence => __('Driving licence'),
            self::BirthCertificate => __('Birth certificate'),
            self::TinCertificate => __('TIN certificate'),
            self::WorkPermit => __('Work permit'),
            self::Visa => __('Visa'),
            self::MedicalCertificate => __('Medical certificate'),
            self::PoliceClearance => __('Police clearance'),
            self::AppointmentLetter => __('Appointment letter'),
            self::Other => __('Other'),
        };
    }
}
