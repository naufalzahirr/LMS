<?php

namespace App\Console\Commands;

use App\Services\B1MathematicsContentInstaller;
use Illuminate\Console\Command;
use Throwable;

final class InstallB1MathematicsContentCommand extends Command
{
    /** @var string */
    protected $signature = 'content:install-b1
        {zip-one : ZIP containing B1-A01 through B1-A30}
        {zip-two : ZIP containing B1-A31 and B1-A32}';

    /** @var string */
    protected $description = 'Install the final Mathematics Phase A Grade I B.1 content package';

    public function handle(B1MathematicsContentInstaller $installer): int
    {
        try {
            $result = $installer->install(
                (string) $this->argument('zip-one'),
                (string) $this->argument('zip-two'),
            );
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Mathematics B.1 content installed successfully.');
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
