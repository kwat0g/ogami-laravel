<?php

declare(strict_types=1);

namespace App\Domains\HR\Recruitment\Enums;

enum RequirementType: string
{
    case NbiClearance = 'nbi_clearance';
    case MedicalCertificate = 'medical_certificate';
    case Tin = 'tin';
    case Sss = 'sss';
    case Philhealth = 'philhealth';
    case Pagibig = 'pagibig';
    case BirthCertificate = 'birth_certificate';
    case Diploma = 'diploma';
    case Transcript = 'transcript';
    case IdPhoto = 'id_photo';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NbiClearance => 'NBI Clearance',
            self::MedicalCertificate => 'Medical Certificate',
            self::Tin => 'TIN',
            self::Sss => 'SSS',
            self::Philhealth => 'PhilHealth',
            self::Pagibig => 'Pag-IBIG',
            self::BirthCertificate => 'Birth Certificate (PSA)',
            self::Diploma => 'Diploma',
            self::Transcript => 'Transcript of Records',
            self::IdPhoto => '2x2 ID Photo',
            self::Other => 'Other',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
