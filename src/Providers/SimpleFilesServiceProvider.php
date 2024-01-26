<?php

namespace Luchavez\SimpleFiles\Providers;

use Illuminate\Database\Eloquent\Model;
use Luchavez\SimpleFiles\Console\Commands\InstallCommand;
use Luchavez\SimpleFiles\Console\Commands\SyncFilesCommand;
use Luchavez\SimpleFiles\Models\File;
use Luchavez\SimpleFiles\Observers\FileObserver;
use Luchavez\SimpleFiles\Services\SimpleFiles;
use Luchavez\StarterKit\Abstracts\BaseStarterKitServiceProvider;

class SimpleFilesServiceProvider extends BaseStarterKitServiceProvider
{
    protected array $commands = [
        SyncFilesCommand::class,
        InstallCommand::class,
    ];

    /**
     * Polymorphism Morph Map
     *
     * @link    https://laravel.com/docs/8.x/eloquent-relationships#custom-polymorphic-types
     *
     * @example [ 'user' => User::class ]
     *
     * @var array
     */
    protected array $morph_map = [];

    /**
     * Laravel Observer Map
     *
     * @link    https://laravel.com/docs/8.x/eloquent#observers
     *
     * @example [ UserObserver::class => User::class ]
     *
     * @var array
     */
    protected array $observer_map = [
        FileObserver::class => File::class,
    ];

    /**
     * Laravel Policy Map
     *
     * @link    https://laravel.com/docs/8.x/authorization#registering-policies
     *
     * @example [ UserPolicy::class => User::class ]
     *
     * @var array
     */
    protected array $policy_map = [];

    /**
     * Laravel Repository Map
     *
     * @example [ UserRepository::class => User::class ]
     *
     * @var array
     */
    protected array $repository_map = [];

    /**
     * Publishable Environment Variables
     *
     * @example [ 'HELLO_WORLD' => true ]
     *
     * @var array
     */
    protected array $env_vars = [
        'SF_PUBLIC_DISK' => '${FILESYSTEM_DISK}',
        'SF_PUBLIC_PREFIX' => 'public',
        'SF_PRIVATE_DISK' => '${FILESYSTEM_DISK}',
        'SF_PRIVATE_PREFIX' => 'private',
        'SF_EXPIRE_AFTER' => '1 day',
        'SF_OVERWRITE_ON_EXISTS' => false,
        'SF_SKIP_UPLOAD_ON_EXISTS' => true,
    ];

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot(): void
    {
        parent::boot();

        if ($user_model = starterKit()->getUserModel()) {
            $user_model::resolveRelationUsing('uploadedFiles', function (Model $model) {
                return $model->hasMany(File::class);
            });
        }

        $this->bootCustomDisks();
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/simple-files.php', 'simple-files');

        // Register the service the package provides.
        $this->app->singleton('simple-files', fn () => new SimpleFiles());

        parent::register();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return ['simple-files'];
    }

    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole(): void
    {
        // Publishing the configuration file.
        $this->publishes(
            [
                __DIR__.'/../../config/simple-files.php' => config_path('simple-files.php'),
            ],
            'simple-files.config'
        );

        // Registering package commands.
        $this->commands($this->commands);
    }

    /**
     * @return bool
     */
    public function areConfigsEnabled(): bool
    {
        return false;
    }

    /**
     * @return void
     */
    public function bootCustomDisks(): void
    {
        // Cancel if config is cached
        if ($this->app->configurationIsCached()) {
            return;
        }

        // Copy default configs
        $default_config = config('filesystems.disks');

        foreach ([true, false] as $bool) {
            $disk_name = $bool ? 'sf-public' : 'sf-private';
            $disk = simpleFiles()->getDriver($bool);
            $prefix = simpleFiles()->getPrefix($bool);

            // Scoped or not
            if ($prefix) {
                $details = [
                    'driver' => 'scoped',
                    'disk' => $disk,
                    'prefix' => $prefix,
                ];
            } else {
                $details = $default_config[$disk] ?? null;
            }

            $default_config[$disk_name] = $details;

            // Read-only
            $details['read-only'] = true;
            $default_config[$disk_name.'-ro'] = $details;
        }

        // Set the new $default_config to config
        config(['filesystems.disks' => $default_config]);
    }
}
