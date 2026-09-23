<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CommunityRole;
use App\Enums\EnergyCommunityMeterPointState;
use App\Enums\EnergyCommunityState;
use App\Events\MeterPointRegistrationAccepted;
use App\Listeners\NotifyMeterPointOwnerOfAcceptedRegistration;
use App\Models\EnergyCommunity;
use App\Models\EnergyCommunityMeterPoint;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegistrationAcceptedEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_transitioning_to_accepted_dispatches_the_event(): void
    {
        Event::fake([MeterPointRegistrationAccepted::class]);

        $manager = User::factory()->create();
        $community = EnergyCommunity::factory()->create(['state' => EnergyCommunityState::Activated]);
        $community->users()->attach($manager->id, ['role' => CommunityRole::Manager]);
        $registration = EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $community->id,
            'state' => EnergyCommunityMeterPointState::MessageReceived,
        ]);
        Sanctum::actingAs($manager);

        $this->postJson("/api/registrations/{$registration->id}/transition", ['state' => 'accepted'])
            ->assertOk();

        Event::assertDispatched(
            MeterPointRegistrationAccepted::class,
            fn (MeterPointRegistrationAccepted $event) => $event->registration->id === $registration->id,
        );
    }

    public function test_transitioning_to_a_non_accepted_state_does_not_dispatch_the_event(): void
    {
        Event::fake([MeterPointRegistrationAccepted::class]);

        $manager = User::factory()->create();
        $community = EnergyCommunity::factory()->create(['state' => EnergyCommunityState::Activated]);
        $community->users()->attach($manager->id, ['role' => CommunityRole::Manager]);
        $registration = EnergyCommunityMeterPoint::factory()->create([
            'energy_community_id' => $community->id,
            'state' => EnergyCommunityMeterPointState::New,
        ]);
        Sanctum::actingAs($manager);

        $this->postJson("/api/registrations/{$registration->id}/transition", ['state' => 'requested'])
            ->assertOk();

        Event::assertNotDispatched(MeterPointRegistrationAccepted::class);
    }

    public function test_the_listener_is_queued(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new NotifyMeterPointOwnerOfAcceptedRegistration);
    }

    public function test_the_listener_logs_the_owner_it_would_notify(): void
    {
        $registration = EnergyCommunityMeterPoint::factory()->create();
        Log::shouldReceive('info')
            ->once()
            ->with(
                'Notifying metering point owner of accepted registration.',
                \Mockery::on(fn (array $context) => $context['user_id'] === $registration->meterPoint->user_id
                    && $context['registration_id'] === $registration->id),
            );

        (new NotifyMeterPointOwnerOfAcceptedRegistration)
            ->handle(new MeterPointRegistrationAccepted($registration));
    }
}
