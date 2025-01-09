<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;
use App\Models\DebitCard;

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

    public function testCustomerCanSeeAListOfDebitCards()
    {
        // get /debit-cards
        $debitCards = DebitCard::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/debit-cards');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment(['id' => $debitCards->first()->id]);
    }

    // public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    // {
    //     // get /debit-cards
    // }

    // public function testCustomerCanCreateADebitCard()
    // {
    //     // post /debit-cards
    // }

    // public function testCustomerCanSeeASingleDebitCardDetails()
    // {
    //     // get api/debit-cards/{debitCard}
    // }

    // public function testCustomerCannotSeeASingleDebitCardDetails()
    // {
    //     // get api/debit-cards/{debitCard}
    // }

    // public function testCustomerCanActivateADebitCard()
    // {
    //     // put api/debit-cards/{debitCard}
    // }

    // public function testCustomerCanDeactivateADebitCard()
    // {
    //     // put api/debit-cards/{debitCard}
    // }

    // public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    // {
    //     // put api/debit-cards/{debitCard}
    // }

    // public function testCustomerCanDeleteADebitCard()
    // {
    //     // delete api/debit-cards/{debitCard}
    // }

    // public function testCustomerCannotDeleteADebitCardWithTransaction()
    // {
    //     // delete api/debit-cards/{debitCard}
    // }

    // Extra bonus for extra tests :)
}
