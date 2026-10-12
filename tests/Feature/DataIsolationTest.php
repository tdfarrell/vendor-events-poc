<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\TicketOrder;
use App\Models\VendorApplication;
use App\Services\Bookings;
use App\Services\Ticketing;
use App\Tenancy\Tenancy;
use LogicException;
use Tests\TestCase;

/**
 * Each organizer's events, vendors and buyers belong to them alone. These
 * tests try every way in from another organizer's subdomain or account.
 */
class DataIsolationTest extends TestCase
{
    private $alpha;

    private $beta;

    private Event $betaEvent;

    private VendorApplication $betaApp;

    private TicketOrder $betaOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alpha = $this->organizer('alpha');
        $this->beta = $this->organizer('beta');
        $this->event($this->alpha, ['title' => 'Alpha Show']);
        $this->betaEvent = $this->event($this->beta, ['title' => 'Beta Fair']);

        Tenancy::as($this->beta, function () {
            [$this->betaApp] = app(Bookings::class)->apply($this->betaEvent, $this->betaEvent->tableTypes()->first(),
                $this->applyData($this->betaEvent, ['business_name' => 'Beta Only Vendor', 'email' => 'secret@beta-vendor.test']));
            [$this->betaOrder] = app(Ticketing::class)->createOrder($this->betaEvent, 'Beta Buyer', 'buyer@beta.test',
                [$this->betaEvent->ticketTypes()->first()->id => 1], 'comp');
        });
    }

    public function test_unknown_subdomain_is_404(): void
    {
        $this->get('http://nobody.lvh.me/')->assertNotFound();
    }

    public function test_public_pages_of_another_organizer_are_not_reachable(): void
    {
        $this->get($this->site($this->beta, '/events/beta-fair'))->assertOk()->assertSee('Beta Fair');

        $this->get($this->site($this->alpha, '/events/beta-fair'))->assertNotFound();
        $this->get($this->site($this->alpha, '/events/beta-fair/apply'))->assertNotFound();
        $this->get($this->site($this->alpha, '/events/beta-fair/tickets'))->assertNotFound();
        $this->post($this->site($this->alpha, '/events/beta-fair/apply'), $this->applyData($this->betaEvent))->assertNotFound();
        $this->get($this->site($this->alpha, '/'))->assertOk()->assertDontSee('Beta Fair');
    }

    public function test_private_links_only_work_on_their_own_subdomain(): void
    {
        $this->get($this->site($this->beta, '/booking/'.$this->betaApp->pay_token))->assertOk();
        $this->get($this->site($this->alpha, '/booking/'.$this->betaApp->pay_token))->assertNotFound();
        $this->get($this->site($this->alpha, '/orders/'.$this->betaOrder->order_token))->assertNotFound();
        $this->get($this->site($this->alpha, '/checkin/'.$this->betaEvent->checkin_token))->assertNotFound();
        $this->get($this->site($this->alpha, '/test-checkout/booking/'.$this->betaApp->pay_token))->assertNotFound();
        $this->post($this->site($this->alpha, '/test-checkout/booking/'.$this->betaApp->pay_token), ['action' => 'pay'])->assertNotFound();

        // The payment really didn't happen.
        Tenancy::as($this->beta, fn () => $this->assertNull($this->betaApp->fresh()->paid_at));
    }

    public function test_staff_cannot_see_another_organizers_admin_records(): void
    {
        $alphaOwner = $this->staff($this->alpha);
        $this->actingAs($alphaOwner);

        $this->get($this->site($this->alpha, '/admin'))->assertOk()->assertSee('Alpha Show')->assertDontSee('Beta Fair');
        $this->get($this->site($this->alpha, '/admin/events/beta-fair'))->assertNotFound();
        $this->get($this->site($this->alpha, '/admin/applications/'.$this->betaApp->id))->assertNotFound();
        $this->post($this->site($this->alpha, '/admin/applications/'.$this->betaApp->id), ['action' => 'decline'])->assertNotFound();
        $this->get($this->site($this->alpha, '/admin/orders/'.$this->betaOrder->id))->assertNotFound();
        $this->get($this->site($this->alpha, '/admin/events/beta-fair/vendors.csv'))->assertNotFound();
    }

    public function test_staff_signed_in_for_one_organizer_are_refused_on_another(): void
    {
        $this->actingAs($this->staff($this->alpha));

        $this->get($this->site($this->beta, '/admin'))->assertForbidden();
        // ...and they're signed out, so the next try just lands on Beta's login page.
        $this->assertGuest();
        $this->get($this->site($this->beta, '/admin/events/beta-fair'))->assertRedirect($this->site($this->beta, '/admin/login'));
    }

    public function test_staff_cannot_sign_in_on_another_organizers_subdomain(): void
    {
        $this->staff($this->alpha);

        $this->post($this->site($this->beta, '/admin/login'), ['email' => 'owner@alpha.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post($this->site($this->alpha, '/admin/login'), ['email' => 'owner@alpha.test', 'password' => 'password'])
            ->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_cannot_book_another_organizers_space_type(): void
    {
        $this->actingAs($this->staff($this->alpha));
        $alphaEvent = Tenancy::as($this->alpha, fn () => Event::first());
        $betaTableType = Tenancy::as($this->beta, fn () => $this->betaEvent->tableTypes()->first());

        $this->post($this->site($this->alpha, '/admin/events/'.$alphaEvent->slug.'/walk-in'), [
            'business_name' => 'Sneaky', 'table_type_id' => $betaTableType->id, 'quantity' => 1, 'method' => 'cash',
        ])->assertSessionHasErrors('table_type_id');

        $this->put($this->site($this->alpha, '/admin/table-types/'.$betaTableType->id), [
            'name' => 'Hijacked', 'price' => '1', 'quantity' => 1, 'max_per_vendor' => 1,
        ])->assertNotFound();
        Tenancy::as($this->beta, fn () => $this->assertSame('6 ft table', $betaTableType->fresh()->name));
    }

    public function test_scoped_queries_fail_closed_without_an_organizer(): void
    {
        $this->assertNull(Tenancy::current());
        $this->assertSame(0, Event::count());
        $this->assertSame(0, VendorApplication::count());

        $this->expectException(LogicException::class);
        Event::create(['type' => 'card_show', 'title' => 'Orphan', 'slug' => 'orphan']);
    }

    public function test_cannot_create_records_for_another_organizer(): void
    {
        // organizer_id can't be mass-assigned: the record lands with the current organizer.
        $planted = Tenancy::as($this->alpha, fn () => Event::create([
            'organizer_id' => $this->beta->id, 'type' => 'card_show', 'title' => 'Planted', 'slug' => 'planted',
        ]));
        $this->assertSame($this->alpha->id, $planted->organizer_id);

        // Forcing it is refused outright.
        $this->expectException(LogicException::class);
        Tenancy::as($this->alpha, fn () => (new Event)->forceFill([
            'organizer_id' => $this->beta->id, 'type' => 'card_show', 'title' => 'Forced', 'slug' => 'forced',
        ])->save());
    }

    public function test_each_organizer_only_counts_its_own_records(): void
    {
        Tenancy::as($this->alpha, function () {
            $this->assertSame(['Alpha Show'], Event::pluck('title')->all());
            $this->assertSame(0, VendorApplication::count());
            $this->assertSame(0, TicketOrder::count());
        });
        Tenancy::as($this->beta, function () {
            $this->assertSame(['Beta Fair'], Event::pluck('title')->all());
            $this->assertSame(1, VendorApplication::count());
        });
    }

    public function test_a_vendor_account_does_not_reveal_other_vendors_or_buyers(): void
    {
        // Alpha's admin can't find Beta's vendor by email, even though vendor accounts are platform-wide.
        $this->actingAs($this->staff($this->alpha));
        $alphaEvent = Tenancy::as($this->alpha, fn () => Event::first());
        $this->get($this->site($this->alpha, '/admin/events/'.$alphaEvent->slug.'?status=all'))
            ->assertOk()->assertDontSee('Beta Only Vendor')->assertDontSee('secret@beta-vendor.test');
    }
}
