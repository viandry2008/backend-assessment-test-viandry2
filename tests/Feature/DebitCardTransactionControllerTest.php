<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DebitCard $debitCard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);
        Passport::actingAs($this->user);
    }

    // Test: Customer can see a list of debit card transactions
    public function testCustomerCanSeeAListOfDebitCardTransactions()
    {
        $transaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $this->debitCard->id
        ]);

        $response = $this->getJson("/api/debit-card-transactions?debit_card_id={$this->debitCard->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment([
            'amount' => $transaction->amount,
        ]);
    }

    // Test: Customer cannot see a list of debit card transactions of other customer's debit card
    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);

        DebitCardTransaction::factory()->create([
            'debit_card_id' => $otherDebitCard->id
        ]);

        $response = $this->getJson("/api/debit-card-transactions?debit_card_id={$otherDebitCard->id}");

        $response->assertStatus(403); // Forbidden, should not access other user's debit card transactions
    }

    // Test: Customer can create a debit card transaction
    public function testCustomerCanCreateADebitCardTransaction()
    {
        $data = [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 5000,
            'currency_code' => 'IDR',
        ];

        $response = $this->postJson("/api/debit-card-transactions", $data);

        $response->assertStatus(201);

        // $response->assertJsonFragment($data);
        // $response->assertJsonFragment([
        //     'amount' => 500,
        //     'currency_code' => 'IDR',
        // ]);
    }

    // Test: Customer cannot create a debit card transaction to another customer's debit card
    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);

        $data = [
            'debit_card_id' => $otherDebitCard->id,
            'amount' => 500,
            'currency_code' => 'IDR',
        ];

        $response = $this->postJson("/api/debit-card-transactions", $data);

        $response->assertStatus(403); // Forbidden, should not access other user's debit card for transaction
    }

    // Test: Customer can see a debit card transaction
    public function testCustomerCanSeeADebitCardTransaction()
    {
        $transaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $this->debitCard->id
        ]);

        $response = $this->getJson("/api/debit-card-transactions/{$transaction->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'amount' => $transaction->amount,
        ]);
    }

    // Test: Customer cannot see a debit card transaction attached to another customer's debit card
    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);
        $transaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $otherDebitCard->id
        ]);

        $response = $this->getJson("/api/debit-card-transactions/{$transaction->id}");

        $response->assertStatus(403); // Forbidden, should not access other user's transaction
    }

    // Extra bonus for extra tests :)
}
