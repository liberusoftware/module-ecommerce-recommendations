<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations_generation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('strategy');
            $table->unsignedSmallInteger('window_days');
            $table->string('state');
            $table->unsignedInteger('candidates_in')->default(0);
            $table->unsignedInteger('asserted')->default(0);
            $table->unsignedInteger('superseded')->default(0);
            $table->unsignedInteger('withheld_below_floor')->default(0);
            $table->unsignedSmallInteger('k_anonymity_floor');
            $table->string('failure_reason')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'strategy', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations_generation_runs');
    }
};
