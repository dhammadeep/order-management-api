<?php

use App\Http\Product\Enums\ProductStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 255)->index();
            $table->string('sku', 100)->unique();
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('stock_quantity');
            $table->string('status', 16)->default(ProductStatus::Active->value)->index();
            $table->timestamps();
            $table->softDeletes()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
