<?php

namespace App\Core\Rules;

use App\Core\Support\PlanLimits;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Validation rule for uploads: the file must fit in what is left of the company's plan storage.
 *
 *     'photo' => ['required', 'image', 'max:2048', new FitsInStorage],
 */
class FitsInStorage implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;   // empty / not a file: the other rules ("nullable", "file") deal with it
        }

        if ($problem = app(PlanLimits::class)->storageProblem((int) $value->getSize())) {
            $fail($problem);
        }
    }
}
