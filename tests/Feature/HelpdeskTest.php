<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketActivity;
use Carbon\Carbon;
use Database\Seeders\DemoSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HelpdeskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function support(): User
    {
        return User::factory()->create(['role' => 'support']);
    }

    private function ticket(User $user, array $data = []): Ticket
    {
        $category = Category::firstOrCreate(['name' => 'Red']);

        return Ticket::create([...['user_id' => $user->id, 'category_id' => $category->id, 'subject' => 'No hay internet', 'description' => 'No podemos abrir el sistema.'], ...$data]);
    }

    public function test_guest_is_redirected_and_registration_is_disabled(): void
    {
        $this->get('/support/tickets')->assertRedirect('/login');
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    public function test_normal_user_cannot_access_other_tickets_or_administration(): void
    {
        $user = User::factory()->create();
        $other = $this->ticket(User::factory()->create());
        $this->actingAs($user)->get('/tickets/'.$other->id)->assertForbidden();
        $this->get('/support/tickets')->assertForbidden();
        $this->get('/support/users')->assertForbidden();
        $this->patch('/tickets/'.$other->id, ['version' => 1, 'priority' => 'urgent'])->assertForbidden();
        $this->post('/tickets/'.$other->id.'/comments', ['message' => 'Hola'])->assertForbidden();
        $this->get('/mis-tickets')->assertInertia(fn (Assert $page) => $page->component('Tickets/Index')->has('tickets.data', 0));
    }

    public function test_create_ticket_ignores_manipulated_status_and_priority_and_notifies_support(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $support = $this->support();
        $category = Category::create(['name' => 'Hardware']);
        $this->actingAs($user)->post('/tickets', ['subject' => 'Mi monitor no funciona', 'description' => 'La pantalla no enciende', 'category_id' => $category->id, 'priority' => 'urgent', 'status' => 'completed'])->assertRedirect();
        $ticket = Ticket::first();
        $this->assertSame('pending', $ticket->status->value);
        $this->assertSame('medium', $ticket->priority->value);
        $this->assertNull($ticket->assigned_to);
        $this->assertSame($user->id, $ticket->user_id);
        $this->assertSame('HD-'.str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT), $ticket->code);
        $this->assertDatabaseHas('ticket_histories', ['ticket_id' => $ticket->id, 'action' => 'created']);
        Notification::assertSentTo($support, TicketActivity::class);
        Notification::assertNotSentTo($user, TicketActivity::class);
    }

    public function test_full_workflow_requires_assignment_and_resolution_and_preserves_previous_close(): void
    {
        Notification::fake();
        $support = $this->support();
        $user = User::factory()->create();
        $ticket = $this->ticket($user);
        $url = '/tickets/'.$ticket->id;
        $this->actingAs($support)->patch($url, ['version' => 1, 'status' => 'in_progress'])->assertSessionHasErrors('status');
        $this->patch($url, ['version' => 1, 'assigned_to' => $support->id])->assertSessionHasNoErrors();
        $this->assertSame('pending', $ticket->fresh()->status->value);
        $this->patch($url, ['version' => 2, 'status' => 'in_progress'])->assertSessionHasNoErrors();
        $started = $ticket->fresh()->started_at;
        $this->patch($url, ['version' => 3, 'status' => 'completed'])->assertSessionHasErrors('resolution');
        $this->patch($url, ['version' => 3, 'status' => 'completed', 'resolution' => 'Se reinició el adaptador de red.'])->assertSessionHasNoErrors();
        $this->assertSame('completed', $ticket->fresh()->status->value);
        $this->assertSame($support->id, $ticket->fresh()->resolved_by);
        $this->patch($url, ['version' => 4, 'status' => 'in_progress'])->assertSessionHasNoErrors();
        $this->assertNull($ticket->fresh()->resolution);
        $this->assertNull($ticket->fresh()->resolved_at);
        $this->assertTrue($started->equalTo($ticket->fresh()->started_at));
        $this->assertSame('Se reinició el adaptador de red.', $ticket->histories()->where('action', 'resolved')->first()->metadata['resolution']);
        $this->assertDatabaseHas('ticket_histories', ['ticket_id' => $ticket->id, 'action' => 'reopened']);
        Notification::assertSentTo($user, TicketActivity::class);
    }

    public function test_stale_version_cannot_overwrite_and_invalid_transitions_are_rejected(): void
    {
        Notification::fake();
        $support = $this->support();
        $ticket = $this->ticket(User::factory()->create());
        $this->actingAs($support)->patch('/tickets/'.$ticket->id, ['version' => 1, 'priority' => 'high'])->assertSessionHasNoErrors();
        $this->patch('/tickets/'.$ticket->id, ['version' => 1, 'priority' => 'low'])->assertSessionHasErrors('version');
        $this->assertSame('high', $ticket->fresh()->priority->value);
        $this->patch('/tickets/'.$ticket->id, ['version' => 2, 'status' => 'completed', 'resolution' => 'Solución'])->assertSessionHasErrors('status');
        $this->patch('/tickets/'.$ticket->id, ['version' => 2, 'priority' => 'low', 'status' => 'in_progress'])->assertSessionHasErrors('status');
    }

    public function test_cannot_assign_normal_or_inactive_users(): void
    {
        $support = $this->support();
        $user = User::factory()->create();
        $ticket = $this->ticket($user);
        $inactive = User::factory()->create(['role' => 'support', 'is_active' => false]);
        $this->actingAs($support)->patch('/tickets/'.$ticket->id, ['version' => 1, 'assigned_to' => $user->id])->assertSessionHasErrors('assigned_to');
        $this->patch('/tickets/'.$ticket->id, ['version' => 1, 'assigned_to' => $inactive->id])->assertSessionHasErrors('assigned_to');
    }

    public function test_attachments_are_private_and_file_limits_are_enforced(): void
    {
        Notification::fake();
        Storage::fake('local');
        $user = User::factory()->create();
        $ticket = $this->ticket($user);
        $this->actingAs($user)->post('/tickets/'.$ticket->id.'/attachments', ['files' => [UploadedFile::fake()->create('diagnostico.txt', 10, 'text/plain')]])->assertSessionHasNoErrors();
        $attachment = $ticket->attachments()->first();
        Storage::disk('local')->assertExists($attachment->path);
        $this->get('/attachments/'.$attachment->id.'/download')->assertDownload('diagnostico.txt');
        $this->get('/attachments/'.$attachment->id.'/preview')->assertNotFound();
        $this->get('/tickets/'.$ticket->id)->assertInertia(fn (Assert $page) => $page->missing('ticket.attachments.0.path')->where('ticket.attachments.0.preview_url', null));
        $this->post('/tickets/'.$ticket->id.'/attachments', ['files' => [UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf')]])->assertSessionHasErrors('files.0');
        $this->post('/tickets/'.$ticket->id.'/attachments', ['files' => array_fill(0, 6, UploadedFile::fake()->create('log.txt', 1, 'text/plain'))])->assertSessionHasErrors('files');
        $this->actingAs(User::factory()->create())->get('/attachments/'.$attachment->id.'/download')->assertForbidden();
    }

    public function test_image_preview_is_inline_and_available_to_the_requester_and_support(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $ticket = $this->ticket($user);
        $content = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j6s0AAAAASUVORK5CYII=');
        Storage::disk('local')->put('tickets/captura.png', $content);
        $attachment = $ticket->attachments()->create([
            'user_id' => $user->id, 'path' => 'tickets/captura.png',
            'original_name' => 'captura.png', 'mime_type' => 'image/png', 'size' => strlen($content),
        ]);

        $this->actingAs($user)->get('/attachments/'.$attachment->id.'/preview')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeaderContains('Content-Disposition', 'inline;')
            ->assertHeaderContains('Cache-Control', 'private')
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertStreamedContent($content);
        $this->get('/tickets/'.$ticket->id)->assertInertia(fn (Assert $page) => $page
            ->where('ticket.attachments.0.preview_url', '/attachments/'.$attachment->id.'/preview')
            ->where('ticket.attachments.0.download_url', '/attachments/'.$attachment->id.'/download')
            ->missing('ticket.attachments.0.path'));
        $this->actingAs($this->support())->get('/attachments/'.$attachment->id.'/preview')->assertOk();
    }

    public function test_image_preview_requires_authentication_and_ticket_access(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $ticket = $this->ticket($owner);
        Storage::disk('local')->put('tickets/privada.jpg', 'private image');
        $attachment = $ticket->attachments()->create([
            'user_id' => $owner->id, 'path' => 'tickets/privada.jpg',
            'original_name' => 'privada.jpg', 'mime_type' => 'image/jpeg', 'size' => 13,
        ]);

        $this->get('/attachments/'.$attachment->id.'/preview')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/attachments/'.$attachment->id.'/preview')->assertForbidden();
    }

    public function test_image_preview_returns_not_found_when_the_stored_file_is_missing(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $attachment = $this->ticket($user)->attachments()->create([
            'user_id' => $user->id, 'path' => 'tickets/missing.png',
            'original_name' => 'missing.png', 'mime_type' => 'image/png', 'size' => 100,
        ]);

        $this->actingAs($user)->get('/attachments/'.$attachment->id.'/preview')->assertNotFound();
    }

    public function test_inactive_account_cannot_log_in_and_existing_session_is_revoked(): void
    {
        $user = User::factory()->create(['is_active' => false, 'password' => 'DemoMesa2026!']);
        $this->post('/login', ['email' => $user->email, 'password' => 'DemoMesa2026!'])->assertSessionHasErrors('email');
        $this->actingAs($user)->get('/mis-tickets')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_last_support_cannot_be_disabled_or_demoted_and_open_tickets_require_reassignment(): void
    {
        $support = $this->support();
        $this->actingAs($support)->patch('/support/users/'.$support->id, ['role' => 'user', 'is_active' => true])->assertSessionHasErrors('role');
        $other = $this->support();
        $ticket = $this->ticket(User::factory()->create(), ['assigned_to' => $support->id]);
        $this->patch('/support/users/'.$support->id, ['role' => 'support', 'is_active' => false])->assertSessionHasErrors('role');
        $this->patch('/support/users/'.$other->id, ['role' => 'support', 'is_active' => false])->assertSessionHasNoErrors();
    }

    public function test_filters_and_local_date_boundaries(): void
    {
        $support = $this->support();
        $user = User::factory()->create();
        $this->ticket($user, ['created_at' => '2026-10-01 03:59:00', 'priority' => 'urgent']);
        $included = $this->ticket($user, ['created_at' => '2026-10-01 04:01:00', 'priority' => 'urgent']);
        $this->actingAs($support)->get('/support/tickets?view=table&from=2026-10-01&to=2026-10-01&priority=urgent')->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1)->where('tickets.data.0.id', $included->id));
        $this->get('/support/tickets?view=table&q='.$included->code)->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1));
    }

    public function test_dashboard_uses_first_closures_and_distinct_tickets(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 16:00:00', 'UTC'));
        $support = $this->support();
        $ticket = $this->ticket(User::factory()->create(), ['created_at' => '2026-10-01 12:00:00']);
        foreach (['2026-10-01 14:00:00', '2026-10-01 15:00:00'] as $at) {
            $ticket->histories()->create(['user_id' => $support->id, 'action' => 'resolved', 'created_at' => $at, 'metadata' => ['resolution' => 'Solución']]);
        }
        $this->actingAs($support)->get('/support/tickets')->assertInertia(fn (Assert $page) => $page->where('metrics.resolved_today', 1)->where('metrics.avg_minutes', 120));
    }

    public function test_comments_are_recorded_and_notifications_belong_to_recipient(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $support = $this->support();
        $ticket = $this->ticket($user, ['assigned_to' => $support->id]);
        $this->actingAs($user)->post('/tickets/'.$ticket->id.'/comments', ['message' => 'Necesito ayuda'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ticket_comments', ['ticket_id' => $ticket->id, 'message' => 'Necesito ayuda']);
        $this->assertDatabaseHas('ticket_histories', ['action' => 'commented']);
        Notification::assertSentTo($support, TicketActivity::class);
        $this->post('/notifications/no-existe/read')->assertNotFound();
    }

    public function test_demo_is_rerunnable_and_has_consistent_relations(): void
    {
        Storage::fake('local');
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);
        $this->assertDatabaseCount('tickets', 18);
        $this->assertDatabaseCount('users', 8);
        $this->assertDatabaseCount('ticket_attachments', 5);
        $this->assertSame(7, Ticket::where('status', 'completed')->count());
        $this->actingAs(User::where('email', 'soporte@demo.test')->first())->get('/support/tickets')->assertOk();
    }

    public function test_support_can_demote_self_and_is_redirected_to_own_workspace(): void
    {
        $support = $this->support();
        $this->support();
        $this->actingAs($support)->patch('/support/users/'.$support->id, ['role' => 'user', 'is_active' => true])
            ->assertRedirect('/dashboard')->assertSessionHasNoErrors();
        $this->assertSame('user', $support->fresh()->role);
        $this->actingAs($support->fresh())->get('/support/users')->assertForbidden();
    }

    public function test_password_recovery_sends_link_and_resets_with_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors()->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
        $token = Password::createToken($user);
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'NuevaMesa2026!', 'password_confirmation' => 'NuevaMesa2026!'])
            ->assertRedirect('/login')->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NuevaMesa2026!', $user->fresh()->password));
    }
}
