<?php

namespace App\Console\Commands;

use App\Services\B3MathematicsContentInstaller;
use Illuminate\Console\Command;
use Throwable;

final class InstallB3MathematicsContentCommand extends Command
{
    /** @var string */
    protected $signature = 'content:install-b3
        {zip : ZIP containing the 26 final active B.3 assets}';

    /** @var string */
    protected $description = 'Install the final Mathematics Phase A Grade I B.3 content package';

    public function handle(B3MathematicsContentInstaller $installer): int
    {
        try {
            $result = $installer->install((string) $this->argument('zip'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Mathematics B.3 content installed successfully.');
        $this->table(
            ['Item', 'Count'],
            [
                ['Unique source images', $result['unique_source_assets']],
                ['Lessons', $result['lessons']],
                ['Lesson content blocks', $result['lesson_blocks']],
                ['Lesson image placements', $result['lesson_assets']],
                ['Formative checkpoints', $result['checkpoints']],
                ['Assessment questions', $result['questions']],
                ['Question image placements', $result['question_assets']],
            ],
        );
        $this->line('Mastery threshold recommendation: 75% (not attached without a class assessment).');

        return self::SUCCESS;
    }
}
