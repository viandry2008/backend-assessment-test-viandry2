<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScheduledRepaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('scheduled_repayments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('loan_id');

            // TODO: Add missing columns here
            $table->decimal('amount', 15, 2);  // Menyimpan jumlah yang dijadwalkan untuk pembayaran
            $table->string('currency_code', 3);  // Menyimpan kode mata uang (misalnya USD, IDR)
            $table->date('due_date');  // Tanggal jatuh tempo pembayaran
            $table->decimal('outstanding_amount', 15, 2);  // Jumlah yang masih terhutang
            $table->enum('status', ['due', 'partial', 'repaid']);  // Status pembayaran

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('loan_id')
                ->references('id')
                ->on('loans')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('scheduled_repayments');
        Schema::enableForeignKeyConstraints();
    }
}
