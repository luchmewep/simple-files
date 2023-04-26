<?php

namespace Luchavez\SimpleFiles\Providers;

use Luchavez\SimpleFiles\Console\Commands\SyncFilesCommand;
use Luchavez\SimpleFiles\Models\File;
use Luchavez\SimpleFiles\Observers\FileObserver;
use Luchavez\SimpleFiles\Repositories\FileRepository;
use Luchavez\SimpleFiles\Services\SimpleFiles;
use Luchavez\StarterKit\Abstracts\BaseStarterKitServiceProvider;
use Illuminate\Database\Eloquent\Model;

class SimpleFilesServiceProvider extends BaseStarterKitServiceProvider
{
    protected array $commands = [
        SyncFilesCommand::class,
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
    protected array $repository_map = [
        FileRepository::class => File::class,
    ];

    /**
     * Publishable Environment Variables
     *
     * @example [ 'HELLO_WORLD' => true ]
     *
     * @var array
     */
    protected array $env_vars = [
        'SF_FILESYSTEM_DISK' => '${FILESYSTEM_DISK}',
        'SF_EXPIRE_AFTER' => '1 day',
        'SF_PUBLIC_DIRECTORY' => 'public',
        'SF_PRIVATE_DIRECTORY' => 'private',
        'SF_OVERWRITE_ON_EXISTS' => false,
        'SF_ROUTES_ENABLED' => true,
    ];

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot(): void
    {
        parent::boot();

        if ($userModel = starter_kit()->getUserModel()) {
            $userModel::resolveRelationUsing('uploadedFiles', function (Model $model) {
                return $model->hasMany(File::class);
            });
        }

        $this->registerCustomDisks();
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
                __DIR__.'/../config/simple-files.php' => config_path('simple-files.php'),
            ],
            'simple-files.config'
        );

        // Registering package commands.
        $this->commands($this->commands);
    }

    /**
     * @return string|null
     */
    public function getRoutePrefix(): ?string
    {
        return 'simple-files';
    }

    /**
     * @return bool
     */
    public function areConfigsEnabled(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public function areRoutesEnabled(): bool
    {
        return config('simple-files.routes_enabled');
    }

    public function registerCustomDisks()
    {
        // Cancel if config is cached
        if ($this->app->configurationIsCached()) {
            return;
        }

        // Copy default configs
        $config = config('filesystems.disks');
        $default_disk = config('filesystems.default');

        // Prepare custom disks configs
        $custom_disks = [
            'sf-public' => [
                'driver' => 'scoped',
                'disk' => $default_disk,
                'prefix' => simpleFiles()->getPublicDirectory(),
            ],
            'sf-private' => [
                'driver' => 'scoped',
                'disk' => $default_disk,
                'prefix' => simpleFiles()->getPrivateDirectory(),
            ],
        ];

        // Add to $config
        foreach ($custom_disks as $driver => $details) {
            $config[$driver] = $details;

            // Add read only option
            $details['read-only'] = true;
            $config[$driver.'-ro'] = $details;
        }

        // Set the new $config to config
        config(['filesystems.disks' => $config]);
    }
}
