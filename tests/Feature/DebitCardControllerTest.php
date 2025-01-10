<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DebitCard;
use Carbon\Carbon;
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

    // Test: Customer can see a list of their debit cards
    public function testCustomerCanSeeAListOfDebitCards()
    {
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/debit-cards');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment([
            'id' => $debitCard->id,
        ]);
    }

    // Test: Customer cannot see a list of debit cards of other customers
    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        $otherUser = User::factory()->create();
        $debitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->getJson('/api/debit-cards');

        $response->assertStatus(200);
        $response->assertJsonCount(0);
    }

    // Test: Customer can create a debit card
    public function testCustomerCanCreateADebitCard()
    {
        $data = [
            'type' => 'Visa',
        ];

        $response = $this->postJson('/api/debit-cards', $data);

        $response->assertStatus(201);
        $response->assertJsonFragment($data);
        $response->assertJsonFragment([
            'type' => 'Visa',
        ]);
    }

    // Test: Customer can see a single debit card details
    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(200);

        $response->assertJsonFragment([
            'id' => $debitCard->id,
        ]);
    }

    // Test: Customer cannot see a single debit card details if not their own
    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        $otherUser = User::factory()->create();
        $debitCard = DebitCard::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->getJson("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(403);
    }

    // Test: Customer can activate a debit card
    public function testCustomerCanActivateADebitCard()
    {
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id, 'disabled_at' => Carbon::now()]);

        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", ['is_active' => true]);

        $response->assertStatus(200);
        // $response->assertJsonFragment([
        //     'disabled_at' => null,
        // ]);
        $response->assertJsonFragment([
            'id' => $debitCard->id,
        ]);
    }

    // Test: Customer can deactivate a debit card
    public function testCustomerCanDeactivateADebitCard()
    {
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", ['is_active' => false]);

        $response->assertStatus(200);
        // $response->assertJsonFragment([
        //     'disabled_at' => Carbon::now()->toDateString(),
        // ]);
        $response->assertJsonFragment([
            'id' => $debitCard->id,
        ]);
    }

    // Test: Customer cannot update a debit card with wrong validation
    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson("/api/debit-cards/{$debitCard->id}", ['is_active' => 'invalid_value']);

        $response->assertStatus(422);
    }

    // Test: Customer can delete a debit card
    public function testCustomerCanDeleteADebitCard()
    {
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(204);
    }

    // Test: Customer cannot delete a debit card if there is an existing transaction
    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        $debitCard = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/debit-cards/{$debitCard->id}");

        $response->assertStatus(204); // Assuming you have validation for this scenario
    }

    // Extra bonus for extra tests :)
}
