<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    private function verifyDebitCardResponse($response, $debitCards)
    {
        $response->assertStatus(200);
        $response->assertJsonCount(count($debitCards)); // Verifikasi jumlah kartu

        foreach ($debitCards as $debitCard) {
            $response->assertJson([
                'data' => [
                    [
                        'id' => $debitCard->id,
                        'number' => (string) $debitCard->number,
                        'type' => $debitCard->type,
                        'expiration_date' => $debitCard->expiration_date->format('Y-m-d'),
                        'is_active' => is_null($debitCard->disabled_at),
                    ]
                ]
            ]);
        }
    }

    public function testCustomerCanSeeAListOfDebitCards()
    {
        $debitCards = DebitCard::factory()->count(2)->create(['user_id' => $this->user->id]);
        $response = $this->getJson('/api/debit-cards');
        // $this->verifyDebitCardResponse($response, $debitCards);

        $otherUser = User::factory()->create();
        $otherUserDebitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);
        $response = $this->getJson('/api/debit-cards');
        $response->assertDontSee($otherUserDebitCard->number);
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        $userDebitCards = DebitCard::factory()->count(2)->create(['user_id' => $this->user->id]);
        $otherUser = User::factory()->create();
        $otherUserDebitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);
        $response = $this->getJson('/api/debit-cards');
        // $this->verifyDebitCardResponse($response, $userDebitCards);
        $response->assertDontSee($otherUserDebitCard->number);
    }

    public function testCustomerCanCreateADebitCard()
    {
        $data = [
            'number' => '5490665795619283',
            'type' => 'MasterCard',
            'expiration_date' => now()->addYear()->format('Y-m-d'),
            'is_active' => true,
        ];

        $response = $this->postJson('/api/debit-cards', $data);
        $response->assertStatus(201);
        // $this->assertDatabaseHas('debit_cards', [
        //     'number' => $data['number'],
        //     'type' => $data['type'],
        //     'expiration_date' => $data['expiration_date'],
        //     'user_id' => $this->user->id,
        // ]);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);
        $response = $this->getJson('/api/debit-cards/' . $debitCard->id);
        $response->assertStatus(200);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        $userDebitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);
        $otherUser = User::factory()->create();
        $otherUserDebitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);
        $response = $this->getJson('/api/debit-cards/' . $otherUserDebitCard->id);
        $response->assertStatus(403);

        $response = $this->getJson('/api/debit-cards/99999');
        $response->assertStatus(404);
    }

    public function testCustomerCanActivateADebitCard()
    {
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id,
            'disabled_at' => now(),
        ]);
        $this->assertNotNull($debitCard->disabled_at);
        $response = $this->putJson('/api/debit-cards/' . $debitCard->id, ['is_active' => true]);
        $response->assertStatus(200);
        $debitCard->refresh();
        $this->assertNull($debitCard->disabled_at);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id,
            'disabled_at' => null,
        ]);
        $this->assertNull($debitCard->disabled_at);
        $response = $this->putJson('/api/debit-cards/' . $debitCard->id, ['is_active' => false]);
        $response->assertStatus(200);
        $debitCard->refresh();
        $this->assertNotNull($debitCard->disabled_at);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id,
            'disabled_at' => null,
        ]);
        $this->assertNull($debitCard->disabled_at);
        $response = $this->putJson('/api/debit-cards/' . $debitCard->id, ['is_active' => 'invalid_value']);
        $response->assertStatus(422);
        $debitCard->refresh();
        $this->assertNull($debitCard->disabled_at);
        $response->assertJsonValidationErrors(['is_active']);
    }

    public function testCustomerCanDeleteADebitCard()
    {
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);
        $response = $this->deleteJson('/api/debit-cards/' . $debitCard->id);
        $response->assertStatus(204);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);
        DebitCardTransaction::factory()->create(['debit_card_id' => $debitCard->id]);
        $response = $this->deleteJson('/api/debit-cards/' . $debitCard->id);
        $response->assertStatus(403);
        // $response->assertJson([
        //     'error' => 'Cannot delete debit card with transactions',
        // ]);
    }
}
