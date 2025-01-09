<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\ScheduledRepayment;
use App\Models\ReceivedRepayment;
use App\Models\User;
use Carbon\Carbon;

class LoanService
{
    /**
     * Create a Loan
     *
     * @param  User  $user
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  int  $terms
     * @param  string  $processedAt
     *
     * @return Loan
     */
    public function createLoan(User $user, int $amount, string $currencyCode, int $terms, string $processedAt): Loan
    {
        // Buat Loan
        $loan = Loan::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'terms' => $terms,
            'outstanding_amount' => $amount,
            'processed_at' => $processedAt,
            'status' => Loan::STATUS_DUE,
        ]);

        // Hitung jumlah pembayaran per termin
        $repaymentAmount = intdiv($amount, $terms); // Pembayaran rata-rata
        $remainingAmount = $amount % $terms; // Sisa pembagian

        // Buat jadwal pembayaran, mulai dari bulan setelah processed_at
        $dueDate = Carbon::parse($processedAt)->addMonth(); // Tambahkan satu bulan ke tanggal processed_at
        $totalScheduledAmount = 0; // Menyimpan jumlah total jadwal pembayaran

        for ($i = 0; $i < $terms; $i++) {
            // Hitung pembayaran untuk termin ini
            $currentAmount = $repaymentAmount;
            
            // Jika ini termin terakhir, tambahkan sisa pembagian untuk mencapai nilai yang diinginkan
            if ($i === $terms - 1) {
                // Termin terakhir akan menerima sisa dari pembagian
                $currentAmount += $remainingAmount;
            }

            // Buat jadwal pembayaran
            ScheduledRepayment::create([
                'loan_id' => $loan->id,
                'amount' => $currentAmount,
                'outstanding_amount' => $currentAmount,
                'currency_code' => $currencyCode,
                'due_date' => $dueDate->toDateString(),
                'status' => ScheduledRepayment::STATUS_DUE,
            ]);

            // Update total amount yang terjadwal
            $totalScheduledAmount += $currentAmount;

            // Tambahkan satu bulan untuk pembayaran berikutnya
            $dueDate->addMonth();
        }

        // Sesuaikan `amount` dan `outstanding_amount` pada Loan agar sesuai dengan total scheduled amount
        if ($totalScheduledAmount !== $amount) {
            // Jika total pembayaran yang terjadwal belum mencapai jumlah yang diinginkan,
            // sesuaikan loan dengan nilai yang benar
            $adjustedAmount = $amount + ($totalScheduledAmount - $amount);
            $loan->update([
                'amount' => $adjustedAmount,
                'outstanding_amount' => $adjustedAmount,
            ]);
        }

        return $loan;

    }


    /**
     * Repay Scheduled Repayments for a Loan
     *
     * @param  Loan  $loan
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  string  $receivedAt
     *
     * @return ReceivedRepayment
     */
    public function repayLoan(Loan $loan, int $amount, string $currencyCode, string $receivedAt): ReceivedRepayment
    {
        $receivedRepayment = ReceivedRepayment::create([
            'loan_id' => $loan->id,
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'received_at' => $receivedAt,
        ]);

        $loan->outstanding_amount -= $amount;
        $loan->save();

        $scheduledRepayments = $loan->scheduledRepayments()
            ->where('status', ScheduledRepayment::STATUS_DUE)
            ->orderBy('due_date')
            ->get();

        foreach ($scheduledRepayments as $scheduledRepayment) {
            if ($amount >= $scheduledRepayment->outstanding_amount) {
                // Full repayment for this scheduled repayment
                $amount -= $scheduledRepayment->outstanding_amount;
                $scheduledRepayment->status = ScheduledRepayment::STATUS_REPAID;
                $scheduledRepayment->outstanding_amount = 0;
            } else {
                // Partial repayment for this scheduled repayment
                $scheduledRepayment->outstanding_amount -= $amount;
                $scheduledRepayment->status = ScheduledRepayment::STATUS_PARTIAL;
                $amount = 0;
            }
            $scheduledRepayment->save();

            // If all received amount has been used, break out of the loop
            if ($amount == 0) break;
        }

        // If the loan is fully repaid, update its status
        if ($loan->outstanding_amount == 0) {
            $loan->status = Loan::STATUS_REPAID;
            $loan->save();
        }

        return $receivedRepayment;
    }
}
