<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLastTwentyRecordsTable extends Migration
{
    public function up(): void
    {
        Schema::create('last_twenty_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('time');
            $table->char('lat', 7);
            $table->char('long', 7);
            $table->char('magnitude', 2);
            $table->char('place', 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('last_twenty_records');
    }
}
