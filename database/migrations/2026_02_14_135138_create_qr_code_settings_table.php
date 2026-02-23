<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('qr_code_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->default('preview-qr');
            $table->json('qrStyling')->default(json_encode([
                'dotsOptions' => [
                    'type' => 'rounded',
                    'color' => '#0d7ff2',
                ],
                'cornersSquareOptions' => [
                    'type' => 'extra-rounded',
                    'color' => '#0d7ff2',
                ],
                'cornersDotOptions' => [
                    'type' => 'dot',
                    'color' => '#0d7ff2',
                ],
                'backgroundOptions' => [
                    'color' => '#ffffff',
                    'margin' => 10,
                ],
                'image' => '',
                'imageOptions' => [
                    'hideBackgroundDots' => true,
                    'imageSize' => 0.4,
                    'margin' => 10,
                ],
            ]));
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qr_code_settings');
    }
};
