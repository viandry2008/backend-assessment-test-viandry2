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
        // Create Loan
        $loan = Loan::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'terms' => $terms,
            'outstanding_amount' => $amount,
            'processed_at' => $processedAt,
            'status' => Loan::STATUS_DUE,
        ]);

        // Create Scheduled Repayments
        $repaymentAmount = intdiv($amount, $terms); // Pembagian jumlah yang rata
        $remainingAmount = $amount % $terms; // Sisa pembagian untuk pembayaran terakhir
        $dueDate = Carbon::parse($processedAt); // Menghitung tanggal mulai

        for ($i = 0; $i < $terms; $i++) {
            $dueDate->addMonth(); // Menambahkan bulan untuk setiap termin
            $currentAmount = $repaymentAmount + ($i === $terms - 1 ? $remainingAmount : 0); // Sesuaikan pembayaran terakhir

            ScheduledRepayment::create([
                'loan_id' => $loan->id,
                'amount' => $currentAmount,
                'outstanding_amount' => $currentAmount,
                'currency_code' => $currencyCode,
                'due_date' => $dueDate->toDateString(),
                'status' => ScheduledRepayment::STATUS_DUE,
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
