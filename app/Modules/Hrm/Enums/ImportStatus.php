<?php

namespace App\Modules\Hrm\Enums;

/** Where an Excel import is (5.6). Pending → Processing → Done or Failed. */
enum ImportStatus: string
{
    case Pending = 'pending';        // uploaded, waiting for the queue worker
    case Processing = 'processing';  // the job is reading / importing
    case Done = 'done';              // every row imported
    case Failed = 'failed';          // nothing imported: see errors

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Waiting'),
            self::Processing => __('Importing…'),
            self::Done => __('Imported'),
            self::Failed => __('Not imported'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending, self::Processing => 'info',
            self::Done => 'success',
            self::Failed => 'danger',
        };
    }

    public function isFinished(): bool
    {
        return $this === self::Done || $this === self::Failed;
    }
}
