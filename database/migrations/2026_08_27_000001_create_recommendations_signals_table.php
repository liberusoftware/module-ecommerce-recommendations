<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations_signals', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');

            // Empty means no subject, which is a legitimate popularity input
            // rather than an error. Nullable would make the natural key below
            // stop arbitrating, because SQL uniqueness ignores nulls.
            $table->string('subject_ref')->default('');

            $table->string('product_ref');

            // The occurrence the signal belongs to — an order, a session. Two
            // lines of one order are one co-occurrence, not two.
            $table->string('group_ref')->default('');

            $table->string('kind');
            $table->string('source_ref');
            $table->timestamp('occurred_at');
            $table->timestamp('retain_until')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'subject_ref', 'source_ref']);
            $table->index(['tenant_id', 'kind', 'occurred_at']);
            $table->index(['tenant_id', 'group_ref']);
            $table->index('retain_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations_signals');
    }
};
