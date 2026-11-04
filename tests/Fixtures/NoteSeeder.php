<?php

namespace Tests\Fixtures;

use Illuminate\Database\Seeder;

/** Test-only seeder: one note, safe to run twice. */
class NoteSeeder extends Seeder
{
    public function run(): void
    {
        Note::firstOrCreate(['text' => 'seeded']);
    }
}
