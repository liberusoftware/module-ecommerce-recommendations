<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations_affinities', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('strategy');

            // Empty means the claim has no anchor — popularity is about the
            // store, not about a product to sit beside.
            $table->string('from_ref')->default('');

            $table->string('to_ref');

            // A ratio the strategy can defend, never a count divided by an
            // assumed maximum. Comparison across strategies happens at serve
            // time, against the candidate set actually read.
            $table->decimal('score', 8, 6);

            $table->unsignedInteger('evidence_count');
            $table->unsignedInteger('subject_count');
            $table->string('state');
            $table->foreignId('run_id')->nullable()->constrained('recommendations_generation_runs')->nullOnDelete();
            $table->timestamp('asserted_at');
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();

            // The strategy is part of the key: two strategies may both claim
            // one pair, and neither overwrites the other.
            $table->unique(['tenant_id', 'strategy', 'from_ref', 'to_ref']);
            $table->index(['tenant_id', 'from_ref', 'state', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations_affinities');
    }
};
