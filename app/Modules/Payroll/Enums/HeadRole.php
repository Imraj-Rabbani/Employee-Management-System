<?php

namespace App\Modules\Payroll\Enums;

use App\Core\Enums\HasOptions;

/**
 * Jobs a salary head can do for the payroll run, set in the payroll settings (legacy config keys headTax,
 * headPF, headOvertime ...). E.g. the overtime stage pays into whichever head is mapped to Overtime.
 * The basic is not here: that is salary_heads.is_basic.
 */
enum HeadRole: string
{
    use HasOptions;

    case Tax = 'tax';
    case ProvidentFund = 'provident_fund';
    case Overtime = 'overtime';
    case Lfa = 'lfa';
    case FestivalBonus = 'festival_bonus';
    case Incentive = 'incentive';
    case LeaveEncashment = 'leave_encashment';
    case Arrear = 'arrear';
    case Penalty = 'penalty';
    case LoanInstallment = 'loan_installment';

    public function label(): string
    {
        return match ($this) {
            self::Tax => __('Income tax'),
            self::ProvidentFund => __('Provident fund'),
            self::Overtime => __('Overtime'),
            self::Lfa => __('Leave fare assistance (LFA)'),
            self::FestivalBonus => __('Festival bonus'),
            self::Incentive => __('Incentive'),
            self::LeaveEncashment => __('Leave encashment'),
            self::Arrear => __('Arrear'),
            self::Penalty => __('Penalty'),
            self::LoanInstallment => __('Loan installment'),
        };
    }

    /** What the payroll run does with it, for the settings screen. */
    public function hint(): string
    {
        return match ($this) {
            self::Tax => __('Monthly tax deducted (tax phase).'),
            self::ProvidentFund => __('Employee PF contribution.'),
            self::Overtime => __('Approved overtime is paid here.'),
            self::Lfa => __('Yearly leave fare assistance.'),
            self::FestivalBonus => __('Festival bonus runs pay here.'),
            self::Incentive => __('Incentives are paid here.'),
            self::LeaveEncashment => __('Approved leave encashments are paid here.'),
            self::Arrear => __('Back-dated changes and late joiners.'),
            self::Penalty => __('Disciplinary deductions.'),
            self::LoanInstallment => __('Loan repayments.'),
        };
    }

    /** The head must be of this type (tax is a deduction, overtime an earning ...). */
    public function headType(): HeadType
    {
        return match ($this) {
            self::Tax, self::ProvidentFund, self::Penalty, self::LoanInstallment => HeadType::Deduction,
            default => HeadType::Earning,
        };
    }
}
