<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tables of the database source (ARCHITECTURE.md 5.3). Every row carries
 * its set key, so one connection can host many doc sets.
 *
 * The page, navigation and manifest tables hold what is live; every change,
 * live or not, is a row in larecipe_revisions with its full payload and
 * provenance. larecipe_docs_migrations remembers what each docs migration did,
 * so `migrate:rollback` can put it back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larecipe_pages', function (Blueprint $table) {
            $table->id();
            $table->string('set', 64);
            $table->string('version', 64);
            $table->string('locale', 16);
            $table->string('path', 512);
            $table->string('title')->default('');
            $table->text('description')->nullable();
            $table->json('frontmatter')->nullable();
            $table->longText('body')->nullable();
            $table->string('content_hash', 80)->nullable();
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['set', 'version', 'locale', 'path']);
        });

        Schema::create('larecipe_navigation', function (Blueprint $table) {
            $table->id();
            $table->string('set', 64);
            $table->string('version', 64);
            $table->string('locale', 16);
            $table->json('tree')->nullable();
            $table->string('content_hash', 80)->nullable();
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->timestamps();

            $table->unique(['set', 'version', 'locale']);
        });

        Schema::create('larecipe_manifest', function (Blueprint $table) {
            $table->id();
            $table->string('set', 64)->unique();
            $table->json('manifest')->nullable();
            $table->string('content_hash', 80)->nullable();
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->timestamps();
        });

        Schema::create('larecipe_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('set', 64);
            $table->string('subject_type', 16);
            $table->unsignedBigInteger('subject_id');
            $table->string('operation', 16);
            $table->string('version', 64)->nullable();
            $table->string('locale', 16)->nullable();
            $table->string('path', 512)->nullable();
            $table->string('path_before', 512)->nullable();
            $table->json('payload')->nullable();
            $table->string('content_hash', 80)->nullable();
            $table->string('based_on_hash', 80)->nullable();
            $table->unsignedBigInteger('replaces_id')->nullable();
            $table->json('conflict')->nullable();
            $table->string('status', 16);
            $table->string('actor', 16);
            $table->string('actor_name')->nullable();
            $table->string('actor_ref', 512)->nullable();
            $table->string('channel', 32)->nullable();
            $table->string('migration')->nullable();
            $table->string('summary', 512)->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['set', 'status']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('larecipe_assets', function (Blueprint $table) {
            $table->id();
            $table->string('set', 64);
            $table->string('version', 64);
            $table->string('path', 512);
            $table->string('disk', 64)->nullable();
            $table->string('disk_path', 1024);
            $table->string('mime', 128);
            $table->unsignedBigInteger('size')->default(0);
            $table->string('hash', 80)->nullable();
            $table->timestamps();

            $table->unique(['set', 'version', 'path']);
        });

        Schema::create('larecipe_docs_migrations', function (Blueprint $table) {
            $table->id();
            $table->string('migration')->unique();
            $table->string('set', 64);
            $table->json('operations')->nullable();
            $table->json('conflicts')->nullable();
            $table->timestamp('applied_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larecipe_docs_migrations');
        Schema::dropIfExists('larecipe_assets');
        Schema::dropIfExists('larecipe_revisions');
        Schema::dropIfExists('larecipe_manifest');
        Schema::dropIfExists('larecipe_navigation');
        Schema::dropIfExists('larecipe_pages');
    }
};
