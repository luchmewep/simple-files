<?php

namespace Luchavez\SimpleFiles\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;
use League\Flysystem\StorageAttributes;
use League\MimeTypeDetection\GeneratedExtensionToMimeTypeMap;
use Luchavez\SimpleFiles\Models\File;
use Luchavez\SimpleFiles\Models\Fileable;
use Luchavez\StarterKit\Traits\UsesCommandCustomMessagesTrait;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class SyncFilesCommand
 *
 * @author James Carlo Luchavez <jamescarloluchavez@gmail.com>
 */
class SyncFilesCommand extends Command
{
    use UsesCommandCustomMessagesTrait;

    /**
     * @var int
     */
    protected int $default_chunk_size = 100;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'sf:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync files from filesystem.';

    /**
     * Execute the console command.
     *
     * @return int
     *
     * @throws FilesystemException
     */
    public function handle(): int
    {
        $this->setupOutputFormatters();

        if (App::isProduction()) {
            $production = '<yellow-bold>PRODUCTION</yellow-bold>';
            $this->warning("$production mode detected.");

            if (! $this->confirm("Continue syncing even in $production?", $this->shouldForce())) {
                return self::FAILURE;
            }
            $this->note("Proceeding sync in $production");
        }

        if (File::query()->count()) {
            $tables = '<yellow-bold>files</yellow-bold> and <yellow-bold>fileables</yellow-bold> tables';
            $this->warning("$tables are not empty.");

            if ($this->confirm("Truncate $tables before syncing?", $this->shouldTruncate())) {
                // Disable foreign key constraints first before truncating.
                $this->ongoing("Truncating $tables");
                Schema::disableForeignKeyConstraints();
                Fileable::query()->truncate();
                File::query()->truncate();
                Schema::enableForeignKeyConstraints();
                $this->done("Successfully truncated $tables");
            }
        }

        $this->ongoing('Syncing public files');
        $this->syncFiles(true);

        $this->newLine();
        $this->ongoing('Syncing private files');
        $this->syncFiles(false);

        return self::SUCCESS;
    }

    /**
     * @throws FilesystemException
     */
    public function syncFiles(bool $is_public): void
    {
        $files = simpleFiles()->getFilesystemAdapter($is_public, true)
            ->listContents('', true)
            ->filter(fn (StorageAttributes $attributes) => $attributes->isFile());

        $now = now('utc')->toDateTimeString();
        $map = new GeneratedExtensionToMimeTypeMap();

        $chunks = LazyCollection::make($files)->chunk($this->getChunkSize());
        $size = $chunks->count();

        $chunks->each(function (LazyCollection $collection, int $index) use ($is_public, $map, $now, $size) {
            $data = $collection->map(function (FileAttributes $file) use ($is_public, $map, $now) {
                $metadata = [];
                $path = Str::of($file->path());
                $name = $path->afterLast('/');

                $metadata['uuid'] = Str::uuid();
                $metadata['is_public'] = $is_public;
                $metadata['path'] = $path;
                $metadata['name'] = $name;
                $metadata['size'] = $file->fileSize();
                $metadata['created_at'] = $now;
                $metadata['updated_at'] = $now;

                if ($name->contains('.')) {
                    collect([$name->after('.'), $name->afterLast('.')])
                        ->takeUntil(function ($ext) use (&$metadata, $map) {
                            if ($mime = $map->lookupMimeType($ext)) {
                                $metadata['extension'] = $ext;
                                $metadata['mime_type'] = $mime;
                            }

                            return $mime;
                        });
                } else {
                    $metadata['extension'] = null;
                    $metadata['mime_type'] = 'application/x-empty';
                }

                // URL-related
                $url_expires_at = simpleFiles()->getExpireAfter();
                $metadata['url'] = simpleFiles()->url($is_public, $path, $url_expires_at);

                if (! $is_public) {
                    $metadata['url_expires_at'] = $url_expires_at;
                }

                return $metadata;
            });

            $index++;
            if ($uploaded = File::query()->insertOrIgnore($data->toArray())) {
                $total_count = $collection->count();
                $this->done("(Chunk $index/$size) Successfully created records: $uploaded/$total_count");
            } else {
                $this->failed("(Chunk $index/$size) Failed to create records.");
            }
        });
    }

    protected function getOptions(): array
    {
        return [
            new InputOption('truncate', 't', InputOption::VALUE_NONE, 'Truncate files table before syncing.'),
            new InputOption('force', 'f', InputOption::VALUE_NONE, 'Force syncing even in PRODUCTION mode.'),
            new InputOption('chunk', 'c', InputOption::VALUE_REQUIRED, 'Number of files to upload per batch.', $this->default_chunk_size),
        ];
    }

    /**
     * @return bool
     */
    protected function shouldTruncate(): bool
    {
        return $this->option('truncate');
    }

    /**
     * @return bool
     */
    protected function shouldForce(): bool
    {
        return $this->option('force');
    }

    /**
     * @return int
     */
    protected function getChunkSize(): int
    {
        $size = $this->option('chunk');

        return $size > 0 ? $size : $this->default_chunk_size;
    }
}
