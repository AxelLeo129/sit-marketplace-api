<?php

namespace Tests\Feature;

use App\Enums\PrescriptionStatus;
use App\Enums\Role;
use App\Mail\PrescriptionMail;
use App\Models\DoctorProfile;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PrescriptionTest extends TestCase
{
    use RefreshDatabase;

    private function doctor(): User
    {
        return DoctorProfile::factory()->create()->user;
    }

    private function callCenter(): User
    {
        return User::factory()->role(Role::CallCenter)->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'patient_name' => 'Juan Pérez',
            'patient_phone' => '55551234',
            'patient_email' => 'juan@example.com',
            'symptoms' => 'Fiebre',
            'diagnosis' => 'Faringitis',
            'allows_call_center' => true,
            'items' => [
                ['product_id' => Product::factory()->create()->id, 'dosage_instructions' => '1 cada 8 horas'],
                ['product_id' => Product::factory()->create()->id, 'dosage_instructions' => '1 al día'],
            ],
            ...$overrides,
        ];
    }

    /**
     * Receta con un medicamento emitida por el médico dado.
     */
    private function prescriptionFor(User $doctor, array $attributes = []): Prescription
    {
        $prescription = Prescription::factory()->for($doctor, 'doctor')->create($attributes);
        PrescriptionItem::factory()->for($prescription)->create();

        return $prescription;
    }

    public function test_doctor_issues_a_prescription_dated_today(): void
    {
        // 20:00 en Guatemala ya es el día siguiente en UTC.
        $this->travelTo(Carbon::parse('2026-10-15 20:00', 'America/Guatemala'));

        $this->actingAs($this->doctor())
            ->postJson('/api/prescriptions', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.issued_on', '2026-10-15')
            ->assertJsonPath('data.diagnosis', 'Faringitis')
            ->assertJsonCount(2, 'data.items');
    }

    public function test_only_doctors_issue_prescriptions(): void
    {
        $this->actingAs($this->callCenter())->postJson('/api/prescriptions', $this->payload())->assertForbidden();
    }

    public function test_rejects_inactive_or_repeated_products(): void
    {
        $inactive = Product::factory()->create(['is_active' => false]);
        $product = Product::factory()->create();

        $this->actingAs($this->doctor())
            ->postJson('/api/prescriptions', $this->payload(['items' => [
                ['product_id' => $inactive->id, 'dosage_instructions' => 'x'],
                ['product_id' => $product->id, 'dosage_instructions' => 'x'],
                ['product_id' => $product->id, 'dosage_instructions' => 'x'],
            ]]))
            ->assertJsonValidationErrors(['items.0.product_id', 'items.1.product_id']);
    }

    public function test_requires_at_least_one_product(): void
    {
        $this->actingAs($this->doctor())
            ->postJson('/api/prescriptions', $this->payload(['items' => []]))
            ->assertJsonValidationErrors('items');
    }

    public function test_each_role_sees_only_its_prescriptions(): void
    {
        $doctor = $this->doctor();
        $own = $this->prescriptionFor($doctor);
        $this->prescriptionFor($this->doctor());
        $this->prescriptionFor($doctor, ['allows_call_center' => false]);

        $this->actingAs($doctor)->getJson('/api/prescriptions')->assertJsonCount(2, 'data');
        $this->actingAs($this->callCenter())->getJson('/api/prescriptions')->assertJsonCount(2, 'data');
        $this->actingAs(User::factory()->role(Role::Admin)->create())->getJson('/api/prescriptions')->assertJsonCount(3, 'data');
        $this->actingAs(User::factory()->role(Role::Courier)->create())->getJson('/api/prescriptions')->assertForbidden();

        $this->actingAs($this->doctor())->getJson("/api/prescriptions/{$own->id}")->assertForbidden();
    }

    public function test_call_center_cannot_see_prescriptions_without_authorization(): void
    {
        $prescription = $this->prescriptionFor($this->doctor(), ['allows_call_center' => false]);

        $this->actingAs($this->callCenter())->getJson("/api/prescriptions/{$prescription->id}")->assertForbidden();
    }

    public function test_clinical_data_is_only_visible_to_the_author(): void
    {
        $doctor = $this->doctor();
        $prescription = $this->prescriptionFor($doctor, ['diagnosis' => 'Dato sensible']);

        $this->actingAs($doctor)->getJson("/api/prescriptions/{$prescription->id}")
            ->assertJsonPath('data.diagnosis', 'Dato sensible');

        $this->actingAs($this->callCenter())->getJson("/api/prescriptions/{$prescription->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.diagnosis')
            ->assertJsonMissingPath('data.symptoms');
    }

    public function test_filters_by_status_and_patient(): void
    {
        $doctor = $this->doctor();
        $this->prescriptionFor($doctor, ['patient_name' => 'María Gómez']);
        $this->prescriptionFor($doctor, ['status' => PrescriptionStatus::Cancelled]);

        $this->actingAs($doctor)->getJson('/api/prescriptions?status=cancelled')->assertJsonCount(1, 'data');
        $this->actingAs($doctor)->getJson('/api/prescriptions?search=gómez')->assertJsonCount(1, 'data');
    }

    public function test_doctor_edits_open_prescription_and_replaces_items(): void
    {
        $doctor = $this->doctor();
        $prescription = $this->prescriptionFor($doctor);

        $this->actingAs($doctor)
            ->putJson("/api/prescriptions/{$prescription->id}", $this->payload(['patient_name' => 'Corregido']))
            ->assertOk()
            ->assertJsonPath('data.patient_name', 'Corregido')
            ->assertJsonCount(2, 'data.items');

        $this->assertDatabaseCount('prescription_items', 2);
    }

    public function test_closed_prescriptions_cannot_be_edited(): void
    {
        $doctor = $this->doctor();
        $prescription = $this->prescriptionFor($doctor, ['status' => PrescriptionStatus::Ordered]);

        $this->actingAs($doctor)->putJson("/api/prescriptions/{$prescription->id}", $this->payload())->assertForbidden();
    }

    public function test_call_center_reschedules_and_list_is_ordered_by_callback(): void
    {
        $callCenter = $this->callCenter();
        $later = $this->prescriptionFor($this->doctor());
        $sooner = $this->prescriptionFor($this->doctor());

        $this->actingAs($callCenter)
            ->postJson("/api/prescriptions/{$later->id}/reschedule", ['callback_at' => now()->addDays(2)->toDateTimeString()])
            ->assertOk()
            ->assertJsonPath('data.status', 'rescheduled');
        $this->actingAs($callCenter)
            ->postJson("/api/prescriptions/{$sooner->id}/reschedule", ['callback_at' => now()->addHour()->toDateTimeString()]);

        $this->actingAs($callCenter)->getJson('/api/prescriptions?status=rescheduled')
            ->assertJsonPath('data.0.id', $sooner->id)
            ->assertJsonPath('data.1.id', $later->id);
    }

    public function test_callback_must_be_in_the_future(): void
    {
        $prescription = $this->prescriptionFor($this->doctor());

        $this->actingAs($this->callCenter())
            ->postJson("/api/prescriptions/{$prescription->id}/reschedule", ['callback_at' => now()->subHour()->toDateTimeString()])
            ->assertJsonValidationErrors('callback_at');
    }

    public function test_call_center_cancels_and_cannot_touch_it_again(): void
    {
        $callCenter = $this->callCenter();
        $prescription = $this->prescriptionFor($this->doctor());

        $this->actingAs($callCenter)->postJson("/api/prescriptions/{$prescription->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->actingAs($callCenter)->postJson("/api/prescriptions/{$prescription->id}/cancel")->assertForbidden();
    }

    public function test_doctor_cannot_reschedule_or_cancel(): void
    {
        $doctor = $this->doctor();
        $prescription = $this->prescriptionFor($doctor);

        $this->actingAs($doctor)->postJson("/api/prescriptions/{$prescription->id}/cancel")->assertForbidden();
    }

    public function test_doctor_sends_the_prescription_by_email(): void
    {
        Mail::fake();
        $doctor = $this->doctor();
        $prescription = $this->prescriptionFor($doctor, ['patient_email' => null]);

        $this->actingAs($doctor)->postJson("/api/prescriptions/{$prescription->id}/send")->assertUnprocessable();

        $this->actingAs($doctor)
            ->postJson("/api/prescriptions/{$prescription->id}/send", ['email' => 'paciente@example.com'])
            ->assertOk();

        Mail::assertSent(PrescriptionMail::class, fn (PrescriptionMail $mail) => $mail->hasTo('paciente@example.com'));
        $this->assertSame('paciente@example.com', $prescription->refresh()->patient_email);
    }

    public function test_prescription_email_renders_without_clinical_data(): void
    {
        $prescription = $this->prescriptionFor($this->doctor(), ['diagnosis' => 'Dato sensible']);

        $html = (new PrescriptionMail($prescription))->render();

        $this->assertStringContainsString($prescription->items->first()->product->name, $html);
        $this->assertStringNotContainsString('Dato sensible', $html);
    }

    public function test_only_the_author_can_send_it(): void
    {
        Mail::fake();
        $prescription = $this->prescriptionFor($this->doctor(), ['patient_email' => 'a@example.com']);

        $this->actingAs($this->callCenter())->postJson("/api/prescriptions/{$prescription->id}/send")->assertForbidden();

        Mail::assertNothingSent();
    }
}
