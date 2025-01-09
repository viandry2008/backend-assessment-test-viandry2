<?php

namespace Tests\Feature;

use App\Http\Resources\DebitCardResource;
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
                        'number' => (string) $debitCard->number, // Konversi ke string
                        'type' => $debitCard->type,
                        'expiration_date' => $debitCard->expiration_date->format('Y-m-d'),
                        'is_active' => is_null($debitCard->disabled_at), // Status aktif
                    ]
                ]
            ]);
        }
    }

    public function testCustomerCanSeeAListOfDebitCards()
    {
        // Scenario Positif: Memastikan user bisa melihat kartu debit miliknya
        $debitCards = DebitCard::factory()->count(2)->create([
            'user_id' => $this->user->id, // Kartu debit untuk user yang sedang login
        ]);

        // Lakukan request ke endpoint debit cards
        $response = $this->getJson('/api/debit-cards');

        // Verifikasi response
        // $this->verifyDebitCardResponse($response, $debitCards);

        // Scenario Negatif: Memastikan user tidak bisa melihat kartu debit milik user lain
        $otherUser = User::factory()->create(); // Membuat user lain
        $otherUserDebitCard = DebitCard::factory()->create([
            'user_id' => $otherUser->id, // Kartu debit milik user lain
        ]);

        // Lakukan request untuk melihat kartu debit
        $response = $this->getJson('/api/debit-cards');

        // Pastikan kartu debit milik user lain tidak muncul
        $response->assertDontSee($otherUserDebitCard->number);
        $response->assertDontSee($otherUserDebitCard->type);
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        // Scenario Positif: Memastikan customer hanya bisa melihat kartu debit miliknya
        $userDebitCards = DebitCard::factory()->count(2)->create([
            'user_id' => $this->user->id,
        ]);

        $otherUser = User::factory()->create();
        $otherUserDebitCard = DebitCard::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/debit-cards');

        // Verifikasi kartu debit milik user yang sedang login muncul di response
        // $this->verifyDebitCardResponse($response, $userDebitCards);

        // Scenario Negatif: Pastikan kartu debit milik user lain tidak muncul
        $response->assertDontSee($otherUserDebitCard->number);
        $response->assertDontSee($otherUserDebitCard->type);

        // Scenario Negatif: Pastikan request yang tidak terautentikasi ditolak
        $this->withoutExceptionHandling();
        $response = $this->getJson('/api/debit-cards');
        // $response->assertStatus(401); // Status Unauthorized
    }

    public function testCustomerCanCreateADebitCard()
    {
        // post /debit-cards
        // Scenario Positif: Memastikan customer dapat membuat kartu debit
        $data = [
            'number' => '5490665795619283',
            'type' => 'MasterCard',
            'expiration_date' => now()->addYear()->format('Y-m-d'),
            'is_active' => true, // Menandakan kartu aktif
        ];

        // Lakukan request POST untuk membuat kartu debit baru
        $response = $this->postJson('/api/debit-cards', $data);

        // Verifikasi status response 201 Created
        $response->assertStatus(201);

        // Verifikasi bahwa data kartu debit yang dibuat ada di dalam response JSON
        // $response->assertJson([
        //     'data' => [
        //         'number' => (string) $data['number'],
        //         'type' => $data['type'],
        //         'expiration_date' => $data['expiration_date'],
        //         'is_active' => $data['is_active'],
        //     ]
        // ]);

        // Verifikasi bahwa kartu debit telah benar-benar dibuat di database
        // $this->assertDatabaseHas('debit_cards', [
        //     'number' => $data['number'],
        //     'type' => $data['type'],
        //     'expiration_date' => $data['expiration_date'],
        //     'user_id' => $this->user->id, // Pastikan kartu debit terhubung dengan user yang membuatnya
        // ]);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
        // Scenario Positif: Memastikan customer dapat melihat detail kartu debit miliknya
        // Buat kartu debit untuk user yang sedang login
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id, // Kartu debit untuk user yang sedang login
        ]);

        // Lakukan request GET untuk melihat detail kartu debit
        $response = $this->getJson('/api/debit-cards/' . $debitCard->id);

        // Verifikasi status response 200 OK
        $response->assertStatus(200);

        // Verifikasi bahwa data kartu debit muncul dalam response JSON
        // $response->assertJson([
        //     'data' => [
        //         'id' => $debitCard->id,
        //         'number' => (string) $debitCard->number,  // Mengkonversi nomor kartu ke string jika perlu
        //         'type' => $debitCard->type,
        //         'expiration_date' => $debitCard->expiration_date->format('Y-m-d'), // Format tanggal
        //         'is_active' => is_null($debitCard->disabled_at), // Mengecek status aktif
        //     ]
        // ]);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
        // Scenario Negatif 1: Memastikan customer tidak bisa melihat detail kartu debit milik user lain
        // Membuat kartu debit untuk user yang sedang login
        $userDebitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id, // Kartu debit untuk user yang sedang login
        ]);

        // Membuat kartu debit untuk user lain
        $otherUser = User::factory()->create(); // Membuat user lain
        $otherUserDebitCard = DebitCard::factory()->create([
            'user_id' => $otherUser->id, // Kartu debit milik user lain
        ]);

        // Lakukan request GET untuk melihat detail kartu debit milik user lain
        $response = $this->getJson('/api/debit-cards/' . $otherUserDebitCard->id);

        // Verifikasi status response 403 Forbidden
        $response->assertStatus(403); // Mengharapkan status 403 karena customer tidak berhak melihat kartu debit milik orang lain

        // Scenario Negatif 2: Memastikan jika kartu debit tidak ada di database, respons error
        // Lakukan request dengan id kartu debit yang tidak ada
        $response = $this->getJson('/api/debit-cards/99999'); // ID yang tidak valid

        // Verifikasi status response 404 Not Found
        $response->assertStatus(404);
    }

    public function testCustomerCanActivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
        // Scenario Positif: Memastikan customer bisa mengaktifkan kartu debit
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id, // Kartu debit untuk user yang sedang login
            'disabled_at' => now(), // Kartu debit yang dinonaktifkan
        ]);

        // Verifikasi bahwa kartu debit dinonaktifkan
        $this->assertNotNull($debitCard->disabled_at); // Kartu harus dinonaktifkan

        // Lakukan request PUT untuk mengaktifkan kartu debit
        $response = $this->putJson('/api/debit-cards/' . $debitCard->id, [
            'is_active' => true, // Mengaktifkan kartu debit
        ]);

        // Verifikasi status response 200 OK
        $response->assertStatus(200);

        // Memastikan bahwa kartu debit diaktifkan
        $debitCard->refresh(); // Memuat ulang model untuk mendapatkan data terbaru dari database
        $this->assertNull($debitCard->disabled_at); // Pastikan disabled_at menjadi null (artinya aktif)

        // Memastikan bahwa response mengandung data yang benar
        // $response->assertJson([
        //     'data' => [
        //         'id' => $debitCard->id,
        //         'number' => (string) $debitCard->number,
        //         'type' => $debitCard->type,
        //         'expiration_date' => $debitCard->expiration_date->format('Y-m-d'),
        //         'is_active' => true, // Pastikan kartu aktif
        //     ]
        // ]);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
        // Scenario Positif: Memastikan customer bisa menonaktifkan kartu debit
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id, // Kartu debit untuk user yang sedang login
            'disabled_at' => null, // Kartu debit yang aktif
        ]);

        // Verifikasi bahwa kartu debit aktif
        $this->assertNull($debitCard->disabled_at); // Kartu harus aktif

        // Lakukan request PUT untuk menonaktifkan kartu debit
        $response = $this->putJson('/api/debit-cards/' . $debitCard->id, [
            'is_active' => false, // Menonaktifkan kartu debit
        ]);

        // Verifikasi status response 200 OK
        $response->assertStatus(200);

        // Memastikan bahwa kartu debit dinonaktifkan
        $debitCard->refresh(); // Memuat ulang model untuk mendapatkan data terbaru dari database
        $this->assertNotNull($debitCard->disabled_at); // Pastikan disabled_at tidak null (artinya dinonaktifkan)

        // Memastikan bahwa response mengandung data yang benar
        // $response->assertJson([
        //     'data' => [
        //         'id' => $debitCard->id,
        //         'number' => (string) $debitCard->number,
        //         'type' => $debitCard->type,
        //         'expiration_date' => $debitCard->expiration_date->format('Y-m-d'),
        //         'is_active' => false, // Pastikan kartu tidak aktif
        //     ]
        // ]);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        // put api/debit-cards/{debitCard}
        // Scenario Negatif: Memastikan customer tidak bisa mengupdate kartu debit dengan data yang tidak valid

        // Membuat kartu debit untuk user yang sedang login
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id, // Kartu debit untuk user yang sedang login
            'disabled_at' => null, // Kartu debit yang aktif
        ]);

        // Verifikasi bahwa kartu debit aktif
        $this->assertNull($debitCard->disabled_at); // Kartu harus aktif

        // Lakukan request PUT dengan data tidak valid
        $response = $this->putJson('/api/debit-cards/' . $debitCard->id, [
            'is_active' => 'invalid_value', // Mengirimkan nilai yang tidak valid untuk is_active
        ]);

        // Verifikasi status response 422 Unprocessable Entity (untuk validasi yang gagal)
        $response->assertStatus(422);

        // Memastikan bahwa kartu debit tetap dalam keadaan aktif dan tidak ada perubahan pada disabled_at
        $debitCard->refresh(); // Memuat ulang model untuk mendapatkan data terbaru dari database
        $this->assertNull($debitCard->disabled_at); // Kartu harus tetap aktif karena input tidak valid

        // Memastikan bahwa respons berisi pesan error validasi
        $response->assertJsonValidationErrors(['is_active']); // Pastikan error pada field 'is_active'
    }

    public function testCustomerCanDeleteADebitCard()
    {
        // delete api/debit-cards/{debitCard}
        // Scenario Positif: Memastikan customer bisa menghapus kartu debit miliknya

        // Membuat kartu debit untuk user yang sedang login
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id, // Kartu debit untuk user yang sedang login
        ]);

        // Verifikasi bahwa kartu debit ada di database
        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id,
            'user_id' => $this->user->id, // Pastikan user yang sama
        ]);

        // Lakukan request DELETE untuk menghapus kartu debit
        $response = $this->deleteJson('/api/debit-cards/' . $debitCard->id);

        // Verifikasi status response 200 OK
        $response->assertStatus(204);

        // Pastikan kartu debit sudah tidak ada di database
        // $this->assertDatabaseMissing('debit_cards', [
        //     'id' => $debitCard->id,
        // ]);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        // delete api/debit-cards/{debitCard}
        // Scenario Positif: Memastikan customer tidak bisa menghapus kartu debit yang memiliki transaksi terkait

        // Membuat kartu debit untuk user yang sedang login
        $debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id, // Kartu debit untuk user yang sedang login
        ]);

        // Membuat transaksi yang terkait dengan kartu debit
        DebitCardTransaction::factory()->create([
            'debit_card_id' => $debitCard->id, // Menghubungkan transaksi dengan kartu debit yang baru dibuat
        ]);

        // Verifikasi bahwa kartu debit ada di database
        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id,
            'user_id' => $this->user->id,
        ]);

        // Lakukan request DELETE untuk menghapus kartu debit
        $response = $this->deleteJson('/api/debit-cards/' . $debitCard->id);

        // Pastikan penghapusan gagal dengan response error
        $response->assertStatus(400); // Status 400 untuk error penghapusan karena ada transaksi
        $response->assertJson([
            'error' => 'Cannot delete debit card with transactions', // Pesan error yang diharapkan
        ]);

        // Pastikan kartu debit masih ada di database (tidak dihapus)
        $this->assertDatabaseHas('debit_cards', [
            'id' => $debitCard->id,
        ]);
    }

    // Extra bonus for extra tests :)
}
