<?php

namespace Luchavez\SimpleFiles\DataFactories;

use Illuminate\Database\Eloquent\Builder;
use Luchavez\SimpleFiles\Models\File;
use Luchavez\StarterKit\Abstracts\BaseDataFactory;

/**
 * Class FileDataFactory
 *
 * @author James Carlo Luchavez <jamescarloluchavez@gmail.com>
 */
class FileDataFactory extends BaseDataFactory
{
    /**
     * @var bool|null
     */
    public ?bool $is_public = null;

    /**
     * @var string
     */
    public string $path;

    /**
     * @var int|string|null
     */
    public int|string|null $owner_id = null;

    /**
     * @var string|null
     */
    public ?string $name;

    /**
     * @var string|null
     */
    public ?string $original_name;

    /**
     * @var string|null
     */
    public ?string $extension;

    /**
     * @var string|null
     */
    public ?string $mime_type;

    /**
     * @var string|null
     */
    public ?string $size;

    /**
     * @var string[]
     */
    public array $tags = [];

    /**
     * @var string|null
     */
    public ?string $deleted_at;

    public function getUniqueKeys(): array
    {
        return [
            'is_public',
            'path',
        ];
    }

    /**
     * @return Builder
     *
     * @example User::query()
     */
    public function getBuilder(): Builder
    {
        return File::query();
    }
}
