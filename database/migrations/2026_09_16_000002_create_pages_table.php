<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('pages')->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->string('path')->unique();
            $table->string('template');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->boolean('show_in_nav')->default(false);
            $table->string('nav_anchor')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('hero_heading')->nullable();
            $table->string('hero_subheading', 500)->nullable();
            $table->string('hero_image')->nullable();
            $table->string('circle_image')->nullable();
            $table->string('circle_image_alt')->nullable();
            $table->text('body')->nullable();
            $table->string('card_excerpt', 500)->nullable();
            $table->string('cta_heading')->nullable();
            $table->timestamps();

            $table->unique(['parent_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
