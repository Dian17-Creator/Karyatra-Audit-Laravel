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
        if (!Schema::hasTable('Muser')) {
            Schema::create('Muser', function (Blueprint $table) {
                $table->integer('nid')->autoIncrement();
                $table->string('cemail', 255)->unique();
                $table->string('cnamalengkap', 255);
                $table->string('cperusahaan', 255)->nullable();
                $table->string('cpassword', 255);
                $table->timestamp('dcreated')->useCurrent();
                $table->boolean('fowner')->default(0);
                $table->date('dnonactive')->nullable();
                $table->enum('clevel', ['admin', 'audit'])->default('audit');
                $table->dateTime('demailverified')->nullable();
                $table->char('cverifytokenhash', 64)->nullable()->unique('uq_Muser_verifytokenhash');
                $table->dateTime('dverifyexpires')->nullable();
                $table->unsignedInteger('ntrialauditcreated')->default(0);
                $table->unsignedInteger('ntrialopnamecreated')->default(0);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Muser');
    }
};
