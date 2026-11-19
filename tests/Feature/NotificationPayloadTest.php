<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\LeaseExpiringNotification;
use App\Notifications\PaymentOverdueNotification;
use App\Notifications\TenantInvitationNotification;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketResolvedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Tests\TestCase;

/**
 * Data notifikací v DB mají jednotný tvar pro zvoneček (#47): type, český title a message s typografií.
 */
class NotificationPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_notification_has_type_title_and_czech_message(): void
    {
        $lease = Lease::factory()->create(['start_date' => '2026-10-15', 'end_date' => '2027-10-14']);
        $payment = Payment::factory()->create(['lease_id' => $lease->id, 'amount' => 12500, 'due_date' => '2026-11-15']);
        $ticket = Ticket::factory()->create(['property_id' => $lease->property_id, 'title' => 'Kape kohoutek']);
        $user = User::factory()->tenant()->create();

        $notifications = [
            new LeaseExpiringNotification($lease),
            new PaymentOverdueNotification($payment),
            new TenantInvitationNotification($lease),
            new TicketCreatedNotification($ticket),
            new TicketResolvedNotification($ticket),
        ];

        $data = array_map(fn (Notification $notification): array => $notification->toArray($user), $notifications);
        foreach ($data as $payload) {
            $this->assertIsString($payload['type']);
            $this->assertNotSame('', $payload['title']);
            $this->assertNotSame('', $payload['message']);
        }

        $this->assertStringContainsString("12\u{00A0}500\u{00A0}Kč", $data[1]['message']);
        $this->assertStringContainsString("15.\u{00A0}11.\u{00A0}2026", $data[1]['message']);
        $this->assertStringContainsString("15.\u{00A0}10.\u{00A0}2026", $data[2]['message']);
        $this->assertStringContainsString('„Kape kohoutek“', $data[3]['message']);
    }
}
