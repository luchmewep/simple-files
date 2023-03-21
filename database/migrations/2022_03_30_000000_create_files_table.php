<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * @author James Carlo Luchavez <jamescarloluchavez@gmail.com>
 */

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * Using UUID is highly recommended as identifier for a model.
     * By default, the uuid column is the generated model's default route key.
     * With this, the routes are protected from manually inputted incrementing id's.
     *
     * Adding `softDeletes()` is also another recommended feature.
     * With this, deleted models are getting archived instead of being deleted from database.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create(
            'files',
            static function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignIdFor(starterKit()->getUserModel())->nullable()->constrained();
                $table->string('path')->unique();
                $table->string('name');
                $table->string('mime_type')->nullable()->index();
                $table->string('extension')->nullable()->index();
                $table->unsignedInteger('size')->nullable();
                $table->text('url')->nullable();
                $table->timestamp('url_expires_at')->nullable();
                $table->softDeletes();
                $table->timestamps();
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
