<?php

namespace Luchavez\SimpleFiles\Models;

use Luchavez\SimpleFiles\Traits\HasFileFactoryTrait;
use Luchavez\StarterKit\Traits\UsesUUIDTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class File
 *
 * @method static Builder image(bool $bool) Get image files.
 * @method static Builder extension(string $extension) Get files with specific extension.
 *
 * @author James Carlo Luchavez <jamescarloluchavez@gmail.com>
 */
class File extends Model
{
    use SoftDeletes;
    use UsesUUIDTrait;
    use HasFileFactoryTrait;

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /***** RELATIONSHIPS *****/

    /**
     * @return BelongsTo|null
     */
    public function user(): ?BelongsTo
    {
        if ($userModel = starterKit()->getUserModel()) {
            return $this->belongsTo($userModel);
        }

        return null;
    }

    /***** SCOPES *****/

    /**
     * @param  Builder  $builder
     * @param  bool  $bool
     * @return Builder
     */
    public function scopeImage(Builder $builder, bool $bool = true): Builder
    {
        return $builder->where('mime_type', ($bool ? '' : 'NOT ').'LIKE', 'image%');
    }

    /**
     * @param  Builder  $builder
     * @param  string  $extension
     * @return Builder
     */
    public function scopeExtension(Builder $builder, string $extension): Builder
    {
        return $builder->where('extension', $extension);
    }

    /***** ACCESSORS & MUTATORS *****/

    /**
     * @param $value
     * @return void
     */
    public function setPathAttribute($value): void
    {
        $this->attributes['path'] = trim($value, '/ ');
    }

    /***** OTHERS *****/

    /**
     * @return bool
     */
    public function isPublic(): bool
    {
        return simpleFiles()->isFilePublic($this);
    }

    /**
     * @return bool
     */
    public function isPrivate(): bool
    {
        return ! simpleFiles()->isFilePublic($this);
    }

    /**
     * @return bool
     */
    public function exists(): bool
    {
        return simpleFiles()->exists($this->path);
    }
}
