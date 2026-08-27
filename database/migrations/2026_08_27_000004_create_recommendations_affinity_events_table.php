<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations_affinity_events', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('affinity_id')->constrained('recommendations_affinities')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('from_state')->nullable();
            $table->string('to_state');
            $table->foreignId('run_id')->nullable()->constrained('recommendations_generation_runs')->nullOnDelete();
            $table->timestamp('occurred_at');

            // Arbitrates a concurrent append. The append-only rule needs the
            // model guard as well; an index stops no UPDATE.
            $table->unique(['affinity_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations_affinity_events');
    }
};
