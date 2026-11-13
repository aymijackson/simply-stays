<?php

namespace Modules\Operations\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Bookings\Models\Booking;
use Modules\Listings\Models\Listing;
use Modules\Listings\Models\Property;
use Modules\Operations\Database\Seeders\InventorySnapshotSeeder;
use Modules\Operations\Models\FinanceCategory;
use Modules\Operations\Models\FinanceTransaction;
use Modules\Operations\Models\InventoryItem;
use Modules\Operations\Models\InventoryLocation;
use Modules\Operations\Models\InventoryMovement;
use Modules\Operations\Models\InventoryStock;
use Modules\Operations\Models\Shift;
use Modules\Operations\Models\StaffMember;
use Modules\Operations\Services\BookingRevenueSync;
use Modules\Operations\Services\StockLedger;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private User $otherHost;

    private Property $property;

    private InventoryLocation $store;

    private InventoryLocation $kitchen;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Guest', 'Host', 'Admin', 'Staff'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->host = User::factory()->create();
        $this->host->assignRole('Host');
        $this->otherHost = User::factory()->create();
        $this->otherHost->assignRole('Host');

        $this->property = Property::create([
            'host_id' => $this->host->id,
            'name' => 'Premium Stay',
            'property_type' => 'apartment',
            'address' => '6 Etim Expo Crescent',
            'city' => 'Calabar',
            'country' => 'Nigeria',
        ]);

        $this->store = InventoryLocation::create(['property_id' => $this->property->id, 'name' => 'Main store', 'type' => 'store']);
        $this->kitchen = InventoryLocation::create(['property_id' => $this->property->id, 'name' => 'Main kitchen', 'type' => 'kitchen']);
    }

    private function item(string $name = 'Glass cup', string $kind = 'asset'): InventoryItem
    {
        return InventoryItem::create(['property_id' => $this->property->id, 'name' => $name, 'kind' => $kind]);
    }

    private function staffUser(string $level): array
    {
        $user = User::factory()->create();
        $user->assignRole('Staff');
        $member = StaffMember::create([
            'property_id' => $this->property->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'position' => 'Worker',
            'access_level' => $level,
        ]);

        return [$user, $member];
    }

    public function test_ledger_moves_stock_and_refuses_to_go_negative(): void
    {
        $cups = $this->item();

        StockLedger::record($cups, 'receive', 12, null, $this->store);
        StockLedger::record($cups, 'transfer', 6, $this->store, $this->kitchen);
        StockLedger::record($cups, 'consume', 1, $this->kitchen);

        $this->assertSame(6, StockLedger::quantity($cups, $this->store));
        $this->assertSame(5, StockLedger::quantity($cups, $this->kitchen));
        $this->assertSame(3, InventoryMovement::count());

        $this->expectException(\DomainException::class);
        StockLedger::record($cups, 'transfer', 7, $this->store, $this->kitchen);
    }

    public function test_stock_count_only_records_differences(): void
    {
        $cups = $this->item();
        StockLedger::record($cups, 'receive', 10, null, $this->store);

        $this->assertNull(StockLedger::count($cups, $this->store, 10));
        $movement = StockLedger::count($cups, $this->store, 7);

        $this->assertSame(-3, $movement->quantity);
        $this->assertSame(7, StockLedger::quantity($cups, $this->store));
    }

    public function test_multi_line_movement_form_and_insufficient_stock_error(): void
    {
        $cups = $this->item();
        $pots = $this->item('Cooking pot');
        StockLedger::record($cups, 'receive', 5, null, $this->store);
        StockLedger::record($pots, 'receive', 2, null, $this->store);

        $this->actingAs($this->host)->post(route('ops.inventory.movements.store'), [
            'type' => 'transfer',
            'from_location_id' => $this->store->id,
            'to_location_id' => $this->kitchen->id,
            'lines' => [['item_id' => $cups->id, 'quantity' => 3], ['item_id' => $pots->id, 'quantity' => 2]],
        ])->assertRedirect(route('ops.inventory.movements.index'));

        $this->assertSame(3, StockLedger::quantity($cups, $this->kitchen));
        $this->assertSame(2, StockLedger::quantity($pots, $this->kitchen));

        // Asking for more than is left rolls back the whole entry.
        $this->actingAs($this->host)->post(route('ops.inventory.movements.store'), [
            'type' => 'transfer',
            'from_location_id' => $this->store->id,
            'to_location_id' => $this->kitchen->id,
            'lines' => [['item_id' => $cups->id, 'quantity' => 1], ['item_id' => $pots->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('lines');

        $this->assertSame(2, StockLedger::quantity($cups, $this->store));
    }

    public function test_receiving_stock_can_post_an_expense(): void
    {
        $soap = $this->item('Liquid soap', 'consumable');

        $this->actingAs($this->host)->post(route('ops.inventory.movements.store'), [
            'type' => 'receive',
            'to_location_id' => $this->store->id,
            'lines' => [['item_id' => $soap->id, 'quantity' => 4, 'unit_cost' => '1,500']],
            'post_expense' => 1,
            'supplier' => 'Market',
            'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();

        $expense = FinanceTransaction::sole();
        $this->assertSame('expense', $expense->type);
        $this->assertSame(600000, $expense->amount);
        $this->assertSame(150000, $soap->fresh()->unit_cost_amount);
        $this->assertSame($expense->id, InventoryMovement::sole()->finance_transaction_id);
    }

    public function test_restock_to_par_pulls_from_the_store(): void
    {
        $suite = InventoryLocation::create(['property_id' => $this->property->id, 'name' => 'Classic Suite', 'area' => 'Bedroom', 'type' => 'apartment']);
        $toiletries = $this->item('Toiletries', 'supply');
        StockLedger::record($toiletries, 'receive', 10, null, $this->store);
        StockLedger::record($toiletries, 'receive', 1, null, $suite);
        InventoryStock::where('location_id', $suite->id)->update(['par_level' => 4]);

        $this->actingAs($this->host)
            ->post(route('ops.inventory.locations.restock', $suite), ['store_id' => $this->store->id])
            ->assertRedirect();

        $this->assertSame(4, StockLedger::quantity($toiletries, $suite));
        $this->assertSame(7, StockLedger::quantity($toiletries, $this->store));
    }

    public function test_access_levels(): void
    {
        [$basic] = $this->staffUser('basic');
        [$keeper] = $this->staffUser('inventory');
        [$manager] = $this->staffUser('manager');
        $item = $this->item();

        $this->actingAs($this->otherHost)->get(route('ops.inventory.items.show', $item))->assertForbidden();

        $this->actingAs($basic)->get(route('ops.dashboard'))->assertOk();
        $this->actingAs($basic)->get(route('ops.shifts.index'))->assertOk();
        $this->actingAs($basic)->get(route('ops.inventory.index'))->assertForbidden();

        $this->actingAs($keeper)->get(route('ops.inventory.index'))->assertOk()->assertSee('Glass cup');
        $this->actingAs($keeper)->get(route('ops.inventory.items.create'))->assertForbidden();
        $this->actingAs($keeper)->get(route('ops.finance.index'))->assertForbidden();

        $this->actingAs($manager)->get(route('ops.finance.index'))->assertOk();
        $this->actingAs($manager)->get(route('ops.staff.index'))->assertOk();

        // Managers cannot hand out manager access; the owner can.
        $this->actingAs($manager)->post(route('ops.staff.store'), [
            'name' => 'New Boss', 'position' => 'Manager', 'access_level' => 'manager', 'employment_type' => 'full_time',
        ])->assertSessionHasErrors('access_level');
        $this->actingAs($this->host)->post(route('ops.staff.store'), [
            'name' => 'New Boss', 'position' => 'Manager', 'access_level' => 'manager', 'employment_type' => 'full_time',
        ])->assertSessionHasNoErrors();
    }

    public function test_registering_staff_with_a_login_creates_a_staff_user(): void
    {
        $this->actingAs($this->host)->post(route('ops.staff.store'), [
            'name' => 'Marvellous',
            'position' => 'Cleaner',
            'department' => 'Housekeeping',
            'access_level' => 'basic',
            'employment_type' => 'full_time',
            'salary' => '70,000',
            'create_login' => 1,
            'login_email' => 'marvellous@example.test',
            'login_password' => 'secret-pass-1',
        ])->assertSessionHasNoErrors();

        $member = StaffMember::where('name', 'Marvellous')->sole();
        $this->assertSame(7000000, $member->salary_amount);
        $this->assertTrue($member->user->hasRole('Staff'));
        $this->assertSame('layouts.staff', $member->user->portalLayout());
    }

    public function test_staff_clock_in_and_out_of_their_own_shift_only(): void
    {
        [$user, $member] = $this->staffUser('basic');
        [$other] = $this->staffUser('basic');

        $shift = Shift::create([
            'property_id' => $this->property->id,
            'staff_member_id' => $member->id,
            'starts_at' => now()->subMinutes(30),
            'ends_at' => now()->addHours(7),
            'status' => 'scheduled',
        ]);

        $this->actingAs($other)->post(route('ops.shifts.clock-in', $shift))->assertForbidden();
        $this->actingAs($user)->post(route('ops.shifts.clock-in', $shift))->assertRedirect();
        $this->assertTrue($shift->fresh()->isOnDuty());

        $this->actingAs($user)->post(route('ops.shifts.clock-out', $shift), ['handover_notes' => 'All clean'])->assertRedirect();
        $shift->refresh();
        $this->assertSame('completed', $shift->status);
        $this->assertSame('All clean', $shift->handover_notes);
    }

    public function test_scheduling_repeats_and_overnight_shifts(): void
    {
        [, $member] = $this->staffUser('basic');

        $this->actingAs($this->host)->post(route('ops.shifts.store'), [
            'staff_member_id' => $member->id,
            'date' => '2026-11-16',
            'start_time' => '19:00',
            'end_time' => '07:00',
            'repeat_days' => 2,
        ])->assertSessionHasNoErrors();

        $shifts = Shift::orderBy('starts_at')->get();
        $this->assertCount(3, $shifts);
        $this->assertSame('2026-11-17 07:00', $shifts[0]->ends_at->format('Y-m-d H:i'));
        $this->assertSame(720, $shifts[0]->scheduledMinutes());
    }

    public function test_books_report_and_booking_sync_is_idempotent(): void
    {
        FinanceCategory::ensureDefaults();
        $guest = User::factory()->create();
        $listing = Listing::create([
            'property_id' => $this->property->id, 'title' => 'Classic Suite', 'slug' => 'classic-suite',
            'base_price_amount' => 5000000, 'currency' => 'NGN', 'max_guests' => 2, 'bedrooms' => 1, 'bathrooms' => 1, 'status' => 'published',
        ]);
        Booking::create([
            'listing_id' => $listing->id, 'guest_id' => $guest->id, 'check_in' => '2026-11-03', 'check_out' => '2026-11-05',
            'guests_count' => 1, 'status' => 'confirmed', 'total_amount' => 10000000, 'currency' => 'NGN',
        ]);

        $this->assertSame(1, BookingRevenueSync::sync($this->property));
        $this->assertSame(0, BookingRevenueSync::sync($this->property));

        $this->actingAs($this->host)->post(route('ops.finance.store'), [
            'type' => 'expense',
            'category_id' => FinanceCategory::bySlug('fuel')->id,
            'amount' => '25,000',
            'occurred_on' => '2026-11-04',
            'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();

        // An expense filed under an income category is rejected.
        $this->actingAs($this->host)->post(route('ops.finance.store'), [
            'type' => 'expense',
            'category_id' => FinanceCategory::bySlug('room-revenue')->id,
            'amount' => '100',
            'occurred_on' => '2026-11-04',
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('category_id');

        $this->actingAs($this->host)
            ->get(route('ops.finance.report', ['from' => '2026-11-01', 'to' => '2026-11-30']))
            ->assertOk()
            ->assertSee('Room revenue')
            ->assertSee(number_format(75000, 2));

        $csv = $this->actingAs($this->host)->get(route('ops.finance.export', ['from' => '2026-11-01', 'to' => '2026-11-30']))->streamedContent();
        $this->assertStringContainsString('Diesel & generator', $csv);
    }

    public function test_notebook_snapshot_imports_and_is_consistent(): void
    {
        Carbon::setTestNow('2026-11-13 10:00');
        $this->resolveSeeder()->seedFor($this->property);

        $store = InventoryLocation::where('property_id', $this->property->id)->where('name', 'Main store')->where('id', '!=', $this->store->id)->sole();
        $item = fn (string $name) => InventoryItem::where('property_id', $this->property->id)->where('name', $name)->sole();

        // Figures written in the notebook's remarks column.
        $this->assertSame(85, StockLedger::quantity($item('Plastic plate'), $store));
        $this->assertSame(29, StockLedger::quantity($item('"Mind your step" sticker'), $store));
        $this->assertSame(2, StockLedger::quantity($item('Umbrella'), $store));
        $this->assertSame(1, StockLedger::quantity($item('Duvet (4/6)'), $store));
        $this->assertSame(9, StockLedger::quantity($item('Book: Success All the Way'), $store));

        $this->assertSame('supply', $item('Toiletries')->kind);
        $this->assertSame('consumable', $item('Detergent')->kind);
        $this->assertSame('asset', $item('Hisense fridge')->kind);
        $this->assertSame(6, InventoryLocation::where('property_id', $this->property->id)->where('type', 'apartment')->distinct()->count('name'));

        // "Madam's room" in the notebook is the King Suite, not a location of its own.
        $this->assertFalse(InventoryLocation::where('name', "Madam's room")->exists());
        $kingLiving = InventoryLocation::where('name', 'King Suite')->where('area', 'Living room')->sole();
        $this->assertSame(2, StockLedger::quantity($item('Umbrella'), $kingLiving));

        // Every stock line equals the sum of its movements.
        foreach (InventoryStock::with('item')->get() as $stock) {
            $in = InventoryMovement::where('item_id', $stock->item_id)->where('to_location_id', $stock->location_id)->sum('quantity');
            $out = InventoryMovement::where('item_id', $stock->item_id)->where('from_location_id', $stock->location_id)->sum('quantity');
            $this->assertSame((int) ($in - $out), $stock->quantity, "Stock for {$stock->item->name} drifted from its ledger");
        }

        // Running it again does not duplicate anything.
        $count = InventoryItem::count();
        $this->resolveSeeder()->seedFor($this->property);
        $this->assertSame($count, InventoryItem::count());

        $this->actingAs($this->host)->get(route('ops.inventory.index'))->assertOk()->assertSee('Toiletries');
        $this->actingAs($this->host)->get(route('ops.inventory.locations.index'))->assertOk()->assertSee('Presidential Suite');
        $this->actingAs($this->host)->get(route('ops.inventory.movements.index'))->assertOk();
        $this->actingAs($this->host)->get(route('ops.dashboard'))->assertOk();
    }

    public function test_migration_merges_madams_room_into_king_suite(): void
    {
        $king = InventoryLocation::create(['property_id' => $this->property->id, 'name' => 'King Suite', 'area' => 'Living room', 'type' => 'apartment']);
        $madam = InventoryLocation::create(['property_id' => $this->property->id, 'name' => "Madam's room", 'type' => 'other']);
        $umbrella = $this->item('Umbrella');
        $rug = $this->item('Rug');
        StockLedger::record($umbrella, 'receive', 4, null, $this->store);
        StockLedger::record($umbrella, 'transfer', 2, $this->store, $madam, ['note' => "Umbrellas carried to Madam's room, 2 left"]);
        StockLedger::record($rug, 'receive', 1, null, $king);
        StockLedger::record($rug, 'receive', 1, null, $madam);

        (require base_path('Modules/Operations/database/migrations/2026_11_14_000000_merge_madams_room_into_king_suite.php'))->up();

        $this->assertNull(InventoryLocation::find($madam->id));
        $this->assertSame(2, StockLedger::quantity($umbrella, $king));
        $this->assertSame(2, StockLedger::quantity($rug, $king));
        $this->assertSame(0, InventoryMovement::where('to_location_id', $madam->id)->count());
        $this->assertTrue(InventoryMovement::where('note', "Umbrellas carried to Madam's room (King Suite), 2 left")->exists());
    }

    public function test_pages_render(): void
    {
        $item = $this->item();
        StockLedger::record($item, 'receive', 3, null, $this->store);
        [, $member] = $this->staffUser('basic');
        $shift = Shift::create(['property_id' => $this->property->id, 'staff_member_id' => $member->id, 'starts_at' => now(), 'ends_at' => now()->addHours(8)]);
        FinanceCategory::ensureDefaults();
        $tx = FinanceTransaction::create(['property_id' => $this->property->id, 'category_id' => FinanceCategory::bySlug('gas')->id, 'type' => 'expense', 'amount' => 100, 'occurred_on' => today(), 'payment_method' => 'cash']);

        $this->actingAs($this->host);
        foreach ([
            route('ops.dashboard'), route('ops.inventory.index'), route('ops.inventory.items.create'), route('ops.inventory.items.show', $item),
            route('ops.inventory.items.edit', $item), route('ops.inventory.locations.index'), route('ops.inventory.locations.create'),
            route('ops.inventory.locations.show', $this->store), route('ops.inventory.locations.edit', $this->store),
            route('ops.inventory.movements.index'), route('ops.inventory.movements.create', ['type' => 'receive']),
            route('ops.staff.index'), route('ops.staff.create'), route('ops.staff.show', $member), route('ops.staff.edit', $member),
            route('ops.shifts.index'), route('ops.shifts.create'), route('ops.shifts.edit', $shift), route('ops.shifts.attendance'),
            route('ops.finance.index'), route('ops.finance.create'), route('ops.finance.edit', $tx), route('ops.finance.report'),
            route('ops.finance.categories'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    private function resolveSeeder(): InventorySnapshotSeeder
    {
        return app(InventorySnapshotSeeder::class);
    }
}
