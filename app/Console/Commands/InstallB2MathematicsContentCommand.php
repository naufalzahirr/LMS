<?php

namespace App\Console\Commands;

use App\Services\B2MathematicsContentInstaller;
use Illuminate\Console\Command;
use Throwable;

final class InstallB2MathematicsContentCommand extends Command
{
    /** @var string */
    protected $signature = 'content:install-b2
        {zip : ZIP containing the 43 final B.2 assets}';

    /** @var string */
    protected $description = 'Install the final Mathematics Phase A Grade I B.2 content package';

    public function handle(B2MathematicsContentInstaller $installer): int
    {
        try {
            $result = $installer->install((string) $this->argument('zip'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Mathematics B.2 content installed successfully.');
        $this->table(
            ['Item', 'Count'],
            [
                ['Unique asset IDs', $result['source_assets']],
                ['Lessons', $result['lessons']],
                ['Lesson content blocks', $result['lesson_blocks']],
                ['Lesson image placements', $result['lesson_assets']],
                ['Formative checkpoints', $result['checkpoints']],
                ['Assessment questions', $result['questions']],
                ['Question image placements', $result['question_assets']],
                ['Class assessment assignments', $result['class_assignments']],
                ['Mastery rules', $result['mastery_rules']],
            ],
        );
        $this->line('Mastery threshold: 75% (6 of 8 correct).');

        return self::SUCCESS;
    }
}
