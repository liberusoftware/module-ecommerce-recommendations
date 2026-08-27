<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations_placements', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('slot');
            $table->string('subject_ref')->default('');
            $table->string('anchor_ref')->default('');
            $table->unsignedSmallInteger('requested');
            $table->unsignedSmallInteger('returned');
            $table->unsignedInteger('candidates_examined');

            // Why an answer is what it is. Null on a placement that returned
            // something; set on every empty one, so silence is never ambiguous.
            $table->string('refusal')->nullable();

            // Whether the catalogue seam answered. Unchecked exclusions are a
            // fact about the answer, not an absence of one.
            $table->boolean('catalogue_checked');
            $table->boolean('cart_checked');
            $table->unsignedBigInteger('seed')->nullable();
            $table->timestamp('served_at');
            $table->timestamps();

            $table->index(['tenant_id', 'slot', 'served_at']);
            $table->index(['tenant_id', 'subject_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations_placements');
    }
};
