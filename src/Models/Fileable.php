<?php

namespace Luchavez\SimpleFiles\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Class Fileable
 *
 * Note:
 * By default, models and factories inside a package need to explicitly connect with each other.
 *
 * @author James Carlo Luchavez <jamescarloluchavez@gmail.com>
 */
class Fileable extends MorphPivot
{
    /**
     * @var string
     */
    protected $table = 'fileables';

    /**
     * @var string[]
     */
    protected $casts = [
        'description' => 'array',
    ];

    protected $hidden = [
        'fileable_id',
        'fileable_type',
        'file_id',
    ];

    /***** RELATIONSHIPS *****/

    /**
     * @return MorphTo
     */
    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }
}
