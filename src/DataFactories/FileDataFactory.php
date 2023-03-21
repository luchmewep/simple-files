<?php

namespace Luchavez\SimpleFiles\DataFactories;

use Luchavez\SimpleFiles\Models\File;
use Luchavez\StarterKit\Abstracts\BaseDataFactory;
// Model
use Illuminate\Database\Eloquent\Builder;

/**
 * Class FileDataFactory
 *
 * @author James Carlo Luchavez <jamescarloluchavez@gmail.com>
 */
class FileDataFactory extends BaseDataFactory
{
    /**
     * @var string
     */
    public string $path;

    /**
     * @var int|string|null
     */
    public int|string|null $user_id;

    /**
     * @var string|null
     */
    public ?string $name;

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
     * @var string|null
     */
    public ?string $deleted_at;

    public function getUniqueKeys(): array
    {
        return [
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
