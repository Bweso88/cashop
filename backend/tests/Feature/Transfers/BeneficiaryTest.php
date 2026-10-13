<?php

namespace Tests\Feature\Transfers;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

class BeneficiaryTest extends TransferTestCase
{
    private array $payload = ['first_name' => 'Marie', 'last_name' => 'Okemba', 'country' => 'CM', 'phone' => '+237690000555', 'city' => 'Yaoundé', 'relationship' => 'Sœur'];

    #[Test]
    public function creation_liste_mise_a_jour_suppression(): void
    {
        $id = $this->postJson('/api/v1/beneficiaries', $this->payload, $this->headers)->assertCreated()
            ->assertJsonPath('display_name', 'Marie O.')->json('id');

        $this->getJson('/api/v1/beneficiaries', $this->headers)->assertOk()->assertJsonCount(4, 'data');
        $this->putJson("/api/v1/beneficiaries/{$id}", [...$this->payload, 'city' => 'Douala'], $this->headers)->assertOk()->assertJsonPath('city', 'Douala');
        $this->deleteJson("/api/v1/beneficiaries/{$id}", [], $this->headers)->assertNoContent();
        $this->getJson('/api/v1/beneficiaries', $this->headers)->assertJsonCount(3, 'data');
    }

    #[Test]
    public function un_doublon_renvoie_le_beneficiaire_existant(): void
    {
        $first = $this->postJson('/api/v1/beneficiaries', $this->payload, $this->headers)->json('id');
        $second = $this->postJson('/api/v1/beneficiaries', [...$this->payload, 'first_name' => ' marie '], $this->headers)->assertOk()->json('id');

        $this->assertSame($first, $second);
    }

    #[Test]
    public function les_donnees_sont_chiffrees_en_base(): void
    {
        $id = $this->postJson('/api/v1/beneficiaries', $this->payload, $this->headers)->json('id');
        $row = DB::table('beneficiaries')->where('id', $id)->first();

        foreach (['first_name' => 'Marie', 'last_name' => 'Okemba', 'phone_e164' => '+237690000555', 'city' => 'Yaoundé'] as $column => $clear) {
            $this->assertStringNotContainsString($clear, $row->{$column}, $column);
        }
    }

    #[Test]
    public function validation(): void
    {
        $this->postJson('/api/v1/beneficiaries', ['first_name' => '', 'country' => 'ZZ', 'phone' => '0690'], $this->headers)
            ->assertStatus(422)->assertJsonValidationErrors(['first_name', 'last_name', 'country', 'phone']);
    }

    #[Test]
    public function inaccessible_a_un_autre_utilisateur(): void
    {
        $id = $this->postJson('/api/v1/beneficiaries', $this->payload, $this->headers)->json('id');
        $other = ['Authorization' => 'Bearer '.$this->tokenFor('client2@cashop.test', 'device-2')];

        $this->getJson("/api/v1/beneficiaries/{$id}", $other)->assertNotFound();
        $this->putJson("/api/v1/beneficiaries/{$id}", $this->payload, $other)->assertNotFound();
        $this->deleteJson("/api/v1/beneficiaries/{$id}", [], $other)->assertNotFound();
    }
}
