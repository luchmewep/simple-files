<?php

namespace Luchavez\SimpleFiles\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Luchavez\StarterKit\Traits\UsesCommandCustomMessagesTrait;

/**
 * Class InstallCommand
 *
 * @author James Carlo Luchavez <jamescarloluchavez@gmail.com>
 */
class InstallCommand extends Command
{
    use UsesCommandCustomMessagesTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'sf:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish configs and migration files.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $simple_files = 'luchavez/simple-files';
        $boilerplate_generator = 'luchavez/boilerplate-generator';

        // Publish env variables
        $this->ongoing("Installing $boilerplate_generator");

        if (! Arr::has(getContentsFromComposerJson(), 'require-dev.'.$boilerplate_generator)) {
            $process = make_process(explode(' ', "composer require $boilerplate_generator --dev"));
            $process->start();
            $process->wait();

            if ($process->isSuccessful()) {
                $this->success("Successfully installed <bold>$boilerplate_generator</bold>");
            } else {
                $this->warning("Failed to install <bold>$boilerplate_generator</bold>");
            }
        }

        $this->call('bg:install');

        // Publish luchavez/simple-files
        $this->ongoing("Publishing $simple_files assets");
        $this->call('vendor:publish', [
            '--tag' => ['simple-files.config'],
        ]);

        // Publish spatie/laravel-tags
        $this->ongoing('Publishing spatie/laravel-tags assets');
        $this->call('vendor:publish', [
            '--tag' => ['tags-migrations', 'tags-config'],
        ]);

        return self::SUCCESS;
    }
}
