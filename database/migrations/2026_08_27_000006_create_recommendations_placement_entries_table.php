<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations_placement_entries', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('placement_id')->constrained('recommendations_placements')->cascadeOnDelete();
            $table->string('product_ref');
            $table->string('strategy');
            $table->decimal('raw_score', 8, 6);
            $table->decimal('normalised_score', 8, 6);
            $table->unsignedInteger('evidence_count');

            // One row shape for a candidate that was shown and one that was
            // not: a position and no reason, or a reason and no position.
            $table->unsignedSmallInteger('position')->nullable();
            $table->string('excluded_for')->nullable();

            $table->unique(['placement_id', 'product_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations_placement_entries');
    }
};
