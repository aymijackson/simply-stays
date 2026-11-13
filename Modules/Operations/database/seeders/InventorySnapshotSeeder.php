<?php

namespace Modules\Operations\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Listings\Models\Listing;
use Modules\Listings\Models\Property;
use Modules\Operations\Models\FinanceCategory;
use Modules\Operations\Models\InventoryItem;
use Modules\Operations\Models\InventoryLocation;
use Modules\Operations\Models\InventoryMovement;
use Modules\Operations\Models\InventoryStock;
use Modules\Operations\Models\StaffMember;
use Modules\Operations\Services\StockLedger;
use RuntimeException;

/**
 * Imports the handwritten store notebook (photographed November 2026):
 *  - the 5 Nov 2026 store inventory and the per-suite room inventories as opening counts,
 *  - the daily inventory log for 5–12 Nov 2026 as stock movements.
 *
 * Movements go through StockLedger, so stock levels equal the sum of the log.
 * Where the notebook issues more of something than the opening count shows,
 * the gap is booked as an extra opening count (noted as "implied") rather
 * than letting stock go negative.
 *
 * Run: php artisan db:seed --class="Modules\Operations\Database\Seeders\InventorySnapshotSeeder"
 * Target property: OPERATIONS_SNAPSHOT_PROPERTY (id or name), else the first property.
 */
class InventorySnapshotSeeder extends Seeder
{
    private const OPENING_NOTE = 'Opening count from notebook, 5 Nov 2026';

    private Property $property;

    /** @var array<string, InventoryLocation> */
    private array $locations = [];

    /** @var array<string, InventoryItem> */
    private array $items = [];

    /** @var array<string, StaffMember> */
    private array $staff = [];

    public function run(): void
    {
        $this->seedFor($this->resolveProperty());
    }

    public function seedFor(Property $property): void
    {
        $this->property = $property;

        if (InventoryMovement::where('property_id', $property->id)->where('note', self::OPENING_NOTE)->exists()) {
            $this->command?->warn("Notebook inventory already imported for {$property->name}; skipping.");

            return;
        }

        FinanceCategory::ensureDefaults();
        $this->createLocations();
        $this->createStaff();
        $this->openingCounts();
        $this->dailyLog();
        $this->reorderLevels();

        $this->command?->info(sprintf(
            'Imported %d items across %d locations for %s.',
            count($this->items),
            count($this->locations),
            $property->name,
        ));
    }

    private function resolveProperty(): Property
    {
        // Read the variable directly too, so an inline value still works when the config is cached.
        $wanted = trim((string) (config('operations.snapshot_property') ?? env('OPERATIONS_SNAPSHOT_PROPERTY', '')));

        $property = match (true) {
            is_numeric($wanted) => Property::find((int) $wanted),
            $wanted !== '' => Property::whereRaw('LOWER(name) = ?', [strtolower($wanted)])->first()
                ?? Property::whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($wanted).'%'])->orderBy('id')->first(),
            default => Property::orderBy('id')->first(),
        };

        if (! $property) {
            $available = Property::orderBy('id')->get(['id', 'name'])->map(fn ($p) => "{$p->id}: {$p->name}");

            throw new RuntimeException($available->isEmpty()
                ? 'This database has no properties yet. Create the property in the app first, then run this seeder again.'
                : "No property matches \"{$wanted}\". Set OPERATIONS_SNAPSHOT_PROPERTY to one of these ids or names: ".$available->implode(', ').'.');
        }

        $this->command?->info("Importing the notebook inventory into: {$property->name} (id {$property->id})");

        return $property;
    }

    // ------------------------------------------------------------------ locations

    private function createLocations(): void
    {
        $order = 0;
        $add = function (string $key, string $name, string $type, ?string $area = null) use (&$order) {
            $this->locations[$key] = InventoryLocation::create([
                'property_id' => $this->property->id,
                'listing_id' => $type === 'apartment' ? $this->listingFor($name) : null,
                'name' => $name,
                'area' => $area,
                'type' => $type,
                'sort_order' => $order += 10,
            ]);
        };

        $add('store', 'Main store', 'store');
        $add('kitchen', 'Main kitchen', 'kitchen');
        $add('laundry', 'Laundry', 'laundry');
        $add('reception', 'Reception', 'reception');
        $add('common', 'Common areas & decor', 'other');
        $add('staff_room', 'Staff quarters', 'staff', 'Staff room');
        $add('boys_room', 'Staff quarters', 'staff', "Boys' room");
        $add('girls_room', 'Staff quarters', 'staff', "Girls' room");
        $add('madam', "Madam's room", 'other');

        foreach ($this->suites() as $suite => $areas) {
            foreach (array_keys($areas) as $area) {
                $add($this->suiteKey($suite, $area), $suite, 'apartment', $area);
            }
        }
    }

    /** Link a suite to a listing with a matching title at the property, when one exists. */
    private function listingFor(string $suite): ?int
    {
        $word = strtolower(Str::before($suite, ' Suite'));

        return Listing::where('property_id', $this->property->id)
            ->whereRaw('LOWER(title) LIKE ?', ["%{$word}%"])
            ->value('id');
    }

    private function suiteKey(string $suite, string $area): string
    {
        return Str::slug($suite).'.'.Str::slug($area);
    }

    // ---------------------------------------------------------------------- staff

    private function createStaff(): void
    {
        $people = [
            'marvellous' => ['Marvellous', 'Cleaner', 'Housekeeping'],
            'emma' => ['Emma', 'Supervisor', 'Management'],
            'ekerette' => ['Ekerette', 'General worker', 'Maintenance'],
            'laundry' => ['Laundry attendant', 'Laundry attendant', 'Laundry'],
        ];

        foreach ($people as $key => [$name, $position, $department]) {
            $this->staff[$key] = StaffMember::create([
                'property_id' => $this->property->id,
                'name' => $name,
                'position' => $position,
                'department' => $department,
                'access_level' => 'basic',
                'notes' => 'Named in the store notebook, Nov 2026. Complete their details.',
            ]);
        }
    }

    // ------------------------------------------------------------- opening counts

    private function openingCounts(): void
    {
        foreach ($this->storeOpening() as $name => $qty) {
            $this->open('store', $name, $qty, par: false);
        }

        // Staff quarters, as described in the log on 9 Nov (bedspreads are moved in by the log itself).
        foreach (['staff_room' => ['Bed' => 2, 'Fan' => 1], 'boys_room' => ['Bed' => 2, 'Chair' => 9, 'Fan' => 1], 'girls_room' => ['Bed' => 2, 'Fan' => 1]] as $room => $contents) {
            foreach ($contents as $name => $qty) {
                $this->open($room, $name, $qty);
            }
        }

        foreach ($this->suites() as $suite => $areas) {
            foreach ($areas as $area => $contents) {
                foreach ($contents as $name => $qty) {
                    $this->open($this->suiteKey($suite, $area), $name, $qty);
                }
            }
        }
    }

    /** Opening count for one line; apartment lines also get it as their par level. */
    private function open(string $location, string $name, int $qty, bool $par = true): void
    {
        $item = $this->item($name);
        $loc = $this->locations[$location];

        if ($qty > 0) {
            StockLedger::record($item, 'adjust', $qty, null, $loc, [
                'occurred_at' => CarbonImmutable::parse('2026-11-05 08:00'),
                'note' => self::OPENING_NOTE,
            ]);
        }

        // "None" in the notebook: keep the line so the room shows it is missing.
        $stock = InventoryStock::firstOrCreate(['item_id' => $item->id, 'location_id' => $loc->id], ['quantity' => 0]);
        if ($par && $loc->type !== 'store') {
            $stock->update(['par_level' => max($qty, $stock->par_level ?? 0) ?: null]);
        }
    }

    // ------------------------------------------------------------------ daily log

    private function dailyLog(): void
    {
        foreach ($this->logEntries() as $i => $entry) {
            [$date, $type, $from, $to, $lines] = $entry;
            $note = $entry[5] ?? null;
            $staff = isset($entry[6]) ? $this->staff[$entry[6]]->id : null;
            $at = CarbonImmutable::parse("{$date} 09:00")->addMinutes($i * 5);

            foreach ($lines as $name => $qty) {
                $item = $this->item($name);
                $fromLoc = $from ? $this->locations[$from] : null;
                $toLoc = $to ? $this->locations[$to] : null;

                if ($fromLoc) {
                    $this->coverShortfall($item, $fromLoc, $qty);
                }

                StockLedger::record($item, $type, $qty, $fromLoc, $toLoc, [
                    'occurred_at' => $at,
                    'note' => $note,
                    'staff_member_id' => $staff,
                ]);
            }
        }
    }

    /** The notebook sometimes issues stock its opening list never counted; book the gap as opening stock. */
    private function coverShortfall(InventoryItem $item, InventoryLocation $location, int $needed): void
    {
        $gap = $needed - StockLedger::quantity($item, $location);

        if ($gap > 0) {
            StockLedger::record($item, 'adjust', $gap, null, $location, [
                'occurred_at' => CarbonImmutable::parse('2026-11-05 08:00'),
                'note' => self::OPENING_NOTE.' (implied by later entries)',
            ]);
        }
    }

    private function reorderLevels(): void
    {
        $levels = [
            'Toiletries' => 20, 'Tooth brush' => 20, 'Bar soap' => 10, 'Toilet roll' => 10, 'Tissue paper' => 2,
            'Liquid soap' => 3, 'Sponge' => 4, 'Detergent' => 1, 'Toilet block' => 2, 'Camphor' => 1,
            'Hypo' => 2, 'Hypo toilet cleaner' => 2, 'Air freshener' => 2, 'Bath gel' => 4, 'Shampoo' => 4,
            'Body lotion' => 4, 'Kitchen rag' => 4, 'Plastic plate' => 20, 'Nose mask' => 2,
        ];

        foreach ($levels as $name => $level) {
            if (isset($this->items[$name])) {
                $this->items[$name]->update(['reorder_level' => $level]);
            }
        }
    }

    // ------------------------------------------------------------------- catalogue

    private function item(string $name): InventoryItem
    {
        if (! isset($this->items[$name])) {
            [$kind, $category, $unit] = $this->classify($name);

            $this->items[$name] = InventoryItem::create([
                'property_id' => $this->property->id,
                'name' => $name,
                'kind' => $kind,
                'category' => $category,
                'unit' => $unit,
                'currency' => config('operations.currency', 'NGN'),
            ]);
        }

        return $this->items[$name];
    }

    /** @return array{0: string, 1: string, 2: string} kind, category, unit */
    private function classify(string $name): array
    {
        $explicit = [
            // Daily guest supplies
            'Toiletries' => ['supply', 'Toiletries & amenities', 'set'],
            'Tooth brush' => ['supply', 'Toiletries & amenities', 'pcs'],
            'Bar soap' => ['supply', 'Toiletries & amenities', 'pcs'],
            'Baby & Me soap' => ['supply', 'Toiletries & amenities', 'pcs'],
            'Bath gel' => ['supply', 'Toiletries & amenities', 'bottle'],
            'Shampoo' => ['supply', 'Toiletries & amenities', 'bottle'],
            'Body lotion' => ['supply', 'Toiletries & amenities', 'bottle'],
            'Toilet roll' => ['supply', 'Toiletries & amenities', 'roll'],
            'Tissue' => ['supply', 'Toiletries & amenities', 'roll'],
            'Tissue paper' => ['supply', 'Toiletries & amenities', 'pack'],
            'Big tissue roll' => ['supply', 'Toiletries & amenities', 'roll'],
            // Consumables
            'Liquid soap' => ['consumable', 'Cleaning', 'bottle'],
            'Detergent' => ['consumable', 'Cleaning', 'pack'],
            'Sponge' => ['consumable', 'Cleaning', 'pcs'],
            'Super cleaner sponge' => ['consumable', 'Cleaning', 'pcs'],
            'Kitchen rag' => ['consumable', 'Cleaning', 'pcs'],
            'Cleaning rag' => ['consumable', 'Cleaning', 'pcs'],
            'Hypo' => ['consumable', 'Cleaning', 'bottle'],
            'Hypo toilet cleaner' => ['consumable', 'Cleaning', 'bottle'],
            'Cleaning chemical (50 L)' => ['consumable', 'Cleaning', 'pcs'],
            'Cleaning chemical (4 L)' => ['consumable', 'Cleaning', 'pcs'],
            'Toilet block' => ['consumable', 'Cleaning', 'pack'],
            'Camphor' => ['consumable', 'Cleaning', 'pack'],
            'Air freshener' => ['consumable', 'Cleaning', 'pcs'],
            'Air wick' => ['consumable', 'Cleaning', 'pcs'],
            'Wonder fresh' => ['consumable', 'Cleaning', 'pcs'],
            'Magic wrap' => ['consumable', 'Kitchenware', 'pack'],
            'Disposable bag' => ['consumable', 'Kitchenware', 'pack'],
            'Plastic plate' => ['consumable', 'Kitchenware', 'pcs'],
            'Candles' => ['consumable', 'Decor & flowers', 'pcs'],
            'Valentine candles' => ['consumable', 'Decor & flowers', 'pcs'],
            '"Mind your step" sticker' => ['consumable', 'Safety & security', 'pcs'],
            'Stickers' => ['consumable', 'Books & stationery', 'pcs'],
            'File' => ['consumable', 'Books & stationery', 'pcs'],
            'Nose mask' => ['consumable', 'Safety & security', 'pcs'],
            'Plaster' => ['consumable', 'Safety & security', 'pack'],
            'Light bulb' => ['consumable', 'Electronics & appliances', 'pcs'],
            'Rope' => ['consumable', 'Laundry', 'bundle'],
            'Clothes peg' => ['asset', 'Laundry', 'pack'],
            // Food & beverage
            'Quaker oats' => ['consumable', 'Food & beverage', 'pack'],
            'Maggi' => ['consumable', 'Food & beverage', 'pcs'],
            'Salt' => ['consumable', 'Food & beverage', 'pack'],
            'Nescafé 3-in-1' => ['consumable', 'Food & beverage', 'pcs'],
            'Nescafé (small)' => ['consumable', 'Food & beverage', 'pcs'],
            'Three Crowns milk' => ['consumable', 'Food & beverage', 'pcs'],
            'Sugar cubes' => ['consumable', 'Food & beverage', 'pack'],
            'Lipton tea' => ['consumable', 'Food & beverage', 'pack'],
            'Bama mayonnaise' => ['consumable', 'Food & beverage', 'bottle'],
            'Milo' => ['consumable', 'Food & beverage', 'pcs'],
            'Seasoning cubes' => ['consumable', 'Food & beverage', 'pack'],
            "King's vegetable oil" => ['consumable', 'Food & beverage', 'bottle'],
            'Pepper, onions & tomatoes' => ['consumable', 'Food & beverage', 'pack'],
            'Bay leaf' => ['consumable', 'Food & beverage', 'pcs'],
            'Fresh lemon' => ['consumable', 'Food & beverage', 'pcs'],
            // Assets that keyword matching would misfile
            'Basket for liquid soap & sponge' => ['asset', 'Kitchenware', 'pcs'],
            'Small basket for flowers' => ['asset', 'Decor & flowers', 'pcs'],
            'Tissue holder' => ['asset', 'Kitchenware', 'pcs'],
            'Hand towel' => ['asset', 'Linen & towels', 'pcs'],
            'Towel set' => ['asset', 'Linen & towels', 'set'],
            'Polo T-shirt (uniform)' => ['asset', 'Uniforms', 'pcs'],
            'Cap (uniform)' => ['asset', 'Uniforms', 'pcs'],
            'Ghana-must-go bag' => ['asset', 'Laundry', 'pcs'],
            'Dresses' => ['asset', 'Other', 'pcs'],
            'Spare keys' => ['asset', 'Safety & security', 'pcs'],
            'X-Force security lock' => ['asset', 'Safety & security', 'pcs'],
            'Umbrella' => ['asset', 'Other', 'pcs'],
            'Royal installation manual' => ['asset', 'Books & stationery', 'pcs'],
            'Television foam' => ['asset', 'Electronics & appliances', 'pcs'],
            'Stapling gun' => ['asset', 'Other', 'pcs'],
        ];

        if (isset($explicit[$name])) {
            return $explicit[$name];
        }
        if (in_array($name, $this->bookTitles(), true)) {
            return ['asset', 'Books & stationery', 'pcs'];
        }

        $rules = [
            'Electronics & appliances' => ['television', 'tv', 'fridge', 'microwave', 'blender', 'toaster', 'fan', 'air conditioner', 'ac remote', 'iron', 'extractor', 'printer', 'telephone', 'light', 'lamp', 'extension', 'cooker', 'remote', 'gas cylinder', 'regulator', 'fixtures'],
            'Linen & towels' => ['towel', 'duvet', 'bedspread', 'pillow'],
            'Decor & flowers' => ['flower', 'vase', 'frame', 'clock', 'candle', 'terracotta'],
            'Kitchenware' => ['plate', 'cup', 'pot', 'pan', 'spoon', 'fork', 'knife', 'bowl', 'tray', 'jug', 'grater', 'opener', 'squeezer', 'filter', 'board', 'saucer', 'glass', 'scissors', 'turning stick', 'lighter', 'hose', 'pipe', 'rack', 'rag holder', 'hook'],
            'Cleaning' => ['brush', 'broom', 'bucket', 'basin', 'scooper', 'waste bin'],
            'Laundry' => ['hanger', 'peg', 'basket'],
            'Furniture & fittings' => ['sofa', 'table', 'chair', 'bed', 'mirror', 'rug', 'mat', 'stand', 'tap'],
        ];

        $lower = strtolower($name);
        foreach ($rules as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    return ['asset', $category, 'pcs'];
                }
            }
        }

        return ['asset', 'Other', 'pcs'];
    }

    // ------------------------------------------------------------------------ data

    private function bookTitles(): array
    {
        return [
            'Book: Effective Time Management', 'Book: Self Concept', 'Book: Effective Study Guide',
            'Book: Sex and Moral Education', 'Book: Etiquettes', 'Book: The Mysteries of the Strength of a Woman',
            'Book: The Female Daily', 'Book: Success All the Way', 'Book: Beyond a Sojourner',
            'Book: Nurturing Infants and Young Children', 'Book: Parenting Bedrock',
        ];
    }

    /** Store inventory as counted on 5 Nov 2026 (two notebook spreads). */
    private function storeOpening(): array
    {
        return [
            // Flowers & decor
            'Hand flowers' => 2, 'Streamer flowers' => 22, 'Stick flowers' => 15, 'Gold & white little flowers' => 5,
            'Gold flower' => 3, 'Rose flower' => 3, 'Coral flower' => 2, 'White flower' => 2, 'Lily small flower (white)' => 4,
            'Pink & orange flower' => 2, 'Pink & green flower' => 1, 'Purple & green flower' => 1, 'Wedding flower (pink & red)' => 5,
            'Foot mat flower' => 3, 'Small cup flower (cactus)' => 4, 'Pot flower (deep pink)' => 1, 'Sugar cane flower' => 2,
            'Pink little flower' => 1, 'Feather flower (orange)' => 16, 'Banana flower' => 4, 'Small light' => 7, 'Light stand' => 2,
            'Small vase pot (brown)' => 2, 'Big vase pot (brown)' => 2, 'Big tall vase pot (black)' => 1, 'Black vase pot' => 2,
            'Quartz clock' => 4, 'Frame' => 9, 'Terracotta pot' => 13, 'Small basket for flowers' => 6, 'Flower basket (medium)' => 4,
            'Valentine flower pot' => 5, 'Valentine candles' => 9, 'Candles' => 7, 'Candle stand' => 8,
            // Books
            'Book: Effective Time Management' => 4, 'Book: Self Concept' => 10, 'Book: Effective Study Guide' => 10,
            'Book: Sex and Moral Education' => 10, 'Book: Etiquettes' => 10, 'Book: The Mysteries of the Strength of a Woman' => 13,
            'Book: The Female Daily' => 10, 'Book: Success All the Way' => 10, 'Book: Beyond a Sojourner' => 10,
            'Book: Nurturing Infants and Young Children' => 4, 'Book: Parenting Bedrock' => 1,
            // Linen
            'Small towel' => 18, 'Medium towel' => 18, 'Big towel' => 18, 'Pillow' => 7, 'Duvet (4/6)' => 2, 'Duvet (6/6)' => 5,
            'Bedspread' => 7,
            // Kitchenware
            'Wine opener' => 2, 'Blender' => 3, 'Bowl' => 2, 'Small bowl' => 4, 'Tray' => 1, 'Tray (white & blue)' => 3,
            'Tray (small, black)' => 3, 'Tray (medium)' => 2, 'Microwave plate' => 2, 'Basket for liquid soap & sponge' => 1,
            'Glass cup' => 12, 'Spoon' => 2, 'Small spoon' => 2, 'Fork' => 16, 'Knife' => 17, 'Kitchen knife' => 5, 'Grater' => 1,
            'Lighter' => 4, 'Reception plate' => 5, 'Opener' => 2, 'Turning stick' => 1, 'Cooking pot' => 10, 'Gas pipe' => 2,
            // Cleaning & supplies
            '"Mind your step" sticker' => 49, 'Sponge' => 8, 'Super cleaner sponge' => 12, 'Air freshener' => 13, 'Air wick' => 1,
            'Wonder fresh' => 16, 'Hard brush' => 3, 'Cleaning rag' => 4, 'Kitchen rag' => 8, 'Broom' => 1, 'Big brush pan' => 2,
            'Scooper' => 4, 'Liquid soap' => 8, 'Toiletries' => 100, 'Tooth brush' => 89, 'Stickers' => 12,
            'Cleaning chemical (50 L)' => 3, 'Cleaning chemical (4 L)' => 3,
            // Mats & fittings
            'Kitchen mat' => 6, 'Shower mat' => 1, 'Door mat' => 7, 'Tap' => 1,
            // Appliances & other
            'Extractor' => 1, 'Television foam' => 1, 'Iron' => 1, 'Extension box' => 2, 'TV stand' => 2,
            'Royal installation manual' => 1, 'Spare keys' => 9, 'Polo T-shirt (uniform)' => 25, 'Cap (uniform)' => 8,
            'Dresses' => 3, 'Ghana-must-go bag' => 3,
        ];
    }

    /** Room inventories per suite, as listed in the notebook. 0 = written as "None". */
    private function suites(): array
    {
        return [
            'Classic Suite' => [
                'Living room' => [
                    'Rug' => 1, 'TCL television' => 1, 'TV guard' => 1, 'Telephone' => 1, 'Flower vase with flowers' => 9, 'Frame' => 1,
                    'TV stand' => 1, 'Standing fan' => 1, 'Sofa' => 3, 'Centre table' => 1, 'Air conditioner' => 1, 'AC remote' => 1,
                    'Couch pillow' => 2, 'Light fixtures' => 1, 'SL TV' => 1, 'Foot mat' => 1,
                ],
                'Kitchen' => [
                    'Hisense fridge' => 1, 'Waste bin' => 1, 'Basket' => 1, 'Glass cup' => 4, 'Tea cup' => 3, 'Microwave' => 1,
                    'Microwave plate' => 4, 'Cooking pot' => 2, 'Frying pan' => 1, 'Chopping board' => 1, 'Tray' => 2, 'Rubber bowl' => 3,
                    'Filter' => 2, 'Kitchen knife' => 5, 'Scissors' => 1, 'Turning stick' => 1, 'Plate rack' => 1, 'Bowl plate' => 6,
                    'Tissue holder' => 1, 'Rag holder' => 1, 'Lime squeezer' => 1, 'Wine opener' => 1, 'Sponge' => 1, 'Liquid soap' => 1,
                    'Jug' => 1, 'Lighter' => 1, 'Grater' => 1, 'Cooking spoon' => 6, 'Cooking spoon stand' => 1, 'Gas cylinder' => 1,
                    'Gas cooker (2-in-1)' => 1, 'Flat plate' => 4,
                ],
                'Bedroom' => [
                    'Towel set' => 3, 'Toiletries' => 4, 'Flower vase with flowers' => 3, 'Sofa' => 1, 'Centre table' => 1,
                    'LG television' => 1, 'Waste bin' => 1, 'Reading table' => 1, 'Reading chair' => 1, 'Bedside table' => 2, 'Frame' => 3,
                    'Air conditioner' => 1, 'AC remote' => 1, 'Pillow' => 6, 'Pillow case' => 6, 'Standing fan' => 1, 'Hanger' => 4,
                    'Fan' => 1, 'Duvet' => 1, 'TV remote' => 1, 'SL TV' => 2, 'Mirror' => 1,
                ],
                'Toilet' => ['Hypo' => 1, 'Hypo toilet cleaner' => 1, 'Toilet brush' => 1, 'Waste bin' => 1, 'Foot mat' => 1],
            ],
            'Standard Suite' => [
                'Room' => [
                    'Rug' => 2, 'TCL television' => 1, 'TV guard' => 1, 'Telephone' => 1, 'Flower vase with flowers' => 3, 'Frame' => 5,
                    'TV stand' => 1, 'Standing fan' => 0, 'Sofa' => 2, 'Air conditioner' => 1, 'AC remote' => 1, 'Couch pillow' => 0,
                    'Light fixtures' => 0, 'SL TV' => 1, 'Extension box' => 2, 'Centre table' => 1, 'Toiletries' => 1, 'Waste bin' => 1,
                    'Pillow' => 3, 'Pillow case' => 3, 'Duvet' => 1, 'Reading table' => 1, 'Reading chair' => 1, 'Towel set' => 3,
                ],
                'Kitchen' => [
                    'Basket' => 1, 'Hisense fridge' => 1, 'Plate rack' => 1, 'Rag holder' => 1, 'Glass cup' => 1, 'Flat plate' => 1,
                    'Bowl plate' => 1, 'Fresh lemon' => 1, 'Sponge' => 1, 'Tissue holder' => 1, 'Microwave' => 1, 'Rug' => 1,
                    'Lime squeezer' => 1,
                ],
                'Toilet' => ['Waste bin' => 1, 'Hypo toilet cleaner' => 1, 'Hypo' => 1, 'Toilet brush' => 1],
            ],
            'King Suite' => [
                'Living room' => [
                    'Rug' => 1, 'Skyrun television' => 1, 'TV guard' => 1, 'Telephone' => 1, 'Flower vase with flowers' => 6, 'Frame' => 1,
                    'Standing fan' => 1, 'Sofa' => 2, 'Centre table' => 1, 'Air conditioner' => 1, 'AC remote' => 1, 'Couch pillow' => 2,
                    'Light fixtures' => 1, 'SL TV' => 1, 'Foot mat' => 1,
                ],
                'Kitchen' => [
                    'Basket' => 1, 'Hisense fridge' => 1, 'Plate rack' => 1, 'Rag holder' => 1, 'Glass cup' => 4, 'Flat plate' => 4,
                    'Bowl plate' => 6, 'Frame' => 1, 'Fresh lemon' => 1, 'Sponge' => 1, 'Tissue holder' => 1, 'Microwave' => 1,
                    'Microwave plate' => 2, 'Blender' => 2, 'Wine opener' => 1, 'Rug' => 1, 'Lime squeezer' => 1, 'Bowl' => 2,
                    'Cooking spoon' => 8, 'Cooking spoon stand' => 1, 'Basket for liquid soap & sponge' => 1, 'Extractor' => 1,
                ],
                'Toilet' => ['Foot mat' => 1, 'Hypo' => 1, 'Hypo toilet cleaner' => 1, 'Waste bin' => 1, 'Toilet brush' => 1, 'Door mat' => 1],
            ],
            'Queen Suite' => [
                'Living room' => [
                    'Rug' => 1, 'LG television' => 1, 'TV guard' => 1, 'Telephone' => 1, 'Flower vase with flowers' => 6, 'Frame' => 1,
                    'TV stand' => 1, 'Standing fan' => 1, 'Sofa' => 1, 'Centre table' => 1, 'Air conditioner' => 1, 'AC remote' => 1,
                    'Couch pillow' => 2, 'Light fixtures' => 1, 'SL TV' => 1,
                ],
                'Bedroom' => [
                    'Towel set' => 3, 'Toiletries' => 4, 'Flower vase with flowers' => 2, 'Sofa' => 1, 'Couch pillow' => 2,
                    'Centre table' => 1, 'Reading table' => 1, 'Reading chair' => 1, 'Bedside table' => 2, 'Frame' => 1,
                    'Air conditioner' => 1, 'Standing fan' => 1, 'Pillow' => 3, 'Pillow case' => 3, 'Hanger' => 4, 'Bedspread' => 1,
                    'Duvet' => 1,
                ],
                'Kitchen' => [
                    'Basket' => 1, 'Disposable bag' => 2, 'Maggi' => 4, 'Waste bin' => 1, 'Flat plate' => 4, 'Deep plate' => 6,
                    'Rag holder' => 2, 'Plate rack' => 1, 'Knife' => 3, 'Fork' => 4, 'Spoon' => 6, 'Kitchen knife' => 5, 'Scissors' => 1,
                    'Cooking spoon' => 6, 'Cooking spoon stand' => 1, 'Lighter' => 1, 'Foot mat' => 1, 'Wine opener' => 1, 'Grater' => 1,
                    'Lime squeezer' => 1, 'Nescafé 3-in-1' => 3, 'Blender' => 2, 'Salt' => 1, 'Glass cup' => 4, 'Microwave' => 1,
                    'Big bowl' => 2, 'Jug' => 1, 'Tea cup' => 2, 'Filter' => 1, 'Gas cylinder' => 1, 'Gas cooker (2-in-1)' => 1,
                    'Frying pan' => 1, 'Tray' => 1, 'Chopping board' => 1, 'Extractor' => 1,
                ],
                'Bedroom 2' => [
                    'Towel set' => 3, 'Toiletries' => 4, 'Flower vase with flowers' => 2, 'Sofa' => 0, 'Centre table' => 0,
                    'LG television' => 1, 'Waste bin' => 1, 'Reading table' => 1, 'Reading chair' => 1, 'Bedside table' => 2, 'Frame' => 1,
                    'Air conditioner' => 1, 'AC remote' => 1, 'Pillow' => 3, 'Pillow case' => 3, 'Standing fan' => 1, 'Hanger' => 4,
                    'Rug' => 1, 'Duvet' => 1, 'TV remote' => 1,
                ],
                'Room toilet' => [
                    'Waste bin' => 1, 'Toilet brush' => 1, 'Door mat' => 1, 'Hypo' => 1, 'Hypo toilet cleaner' => 1, 'Bucket' => 1,
                    'Bathroom scooper' => 1,
                ],
            ],
            'Penthouse Suite' => [
                'Room' => [
                    'Rug' => 2, 'TCL television' => 1, 'LG television' => 1, 'TV guard' => 1, 'Telephone' => 1,
                    'Flower vase with flowers' => 3, 'Frame' => 4, 'TV stand' => 1, 'Standing fan' => 1, 'Sofa' => 1, 'Centre table' => 0,
                    'Air conditioner' => 1, 'AC remote' => 0, 'Couch pillow' => 0, 'Light fixtures' => 1, 'SL TV' => 0, 'Extension box' => 0,
                    'Toiletries' => 1, 'Towel set' => 1, 'Waste bin' => 1, 'Mirror' => 1, 'Hanger' => 4, 'Duvet' => 1, 'Pillow' => 3,
                    'Pillow case' => 3, 'Reading table' => 0, 'Reading chair' => 0,
                ],
                'Kitchen' => [
                    'Skyrun fridge' => 1, 'Egg cup' => 2, 'Jug' => 1, 'Foot mat' => 1, 'Waste bin' => 1, 'Plate rack' => 1,
                    'Tissue holder' => 1, 'Rag holder' => 1, 'Flat plate' => 6, 'Fork' => 4, 'Spoon' => 4, 'Kitchen knife' => 5,
                    'Sponge' => 1, 'Cooking spoon' => 8, 'Basket' => 1, 'Blender' => 2, 'Microwave' => 1, 'Lighter' => 1, 'Opener' => 1,
                    'Serving tray' => 1, 'Grater' => 1, 'Chopping board' => 1, 'Filter' => 1, 'Cooking pot' => 2, 'Frying pan' => 1,
                    'Microwave plate' => 1, 'Bowl' => 3, 'Bowl plate' => 6, 'Scooper' => 1, 'Basket for liquid soap & sponge' => 1,
                    'Extractor' => 1,
                ],
                'Toilet' => [
                    'Waste bin' => 1, 'Tissue' => 1, 'Toilet brush' => 1, 'Hypo toilet cleaner' => 1, 'Hypo' => 1, 'Bucket' => 1,
                    'Bathroom scooper' => 1, 'Foot mat' => 1,
                ],
            ],
            'Presidential Suite' => [
                'Living room' => [
                    'Rug' => 1, 'TCL television' => 1, 'TV guard' => 1, 'Telephone' => 1, 'Flower vase with flowers' => 4, 'Frame' => 1,
                    'TV stand' => 1, 'Standing fan' => 1, 'Sofa' => 2, 'Centre table' => 1, 'Air conditioner' => 1, 'AC remote' => 1,
                    'Couch pillow' => 0, 'Light fixtures' => 1, 'SL TV' => 1,
                ],
                'Toilet' => ['Waste bin' => 1, 'Toilet brush' => 1, 'Door mat' => 1, 'Hypo' => 1, 'Hypo toilet cleaner' => 1],
                'Bedroom' => [
                    'Towel set' => 3, 'Toiletries' => 1, 'Flower vase with flowers' => 4, 'Sofa' => 1, 'Centre table' => 1,
                    'LG television' => 1, 'Waste bin' => 1, 'Reading table' => 1, 'Reading chair' => 1, 'Bedside table' => 2, 'Frame' => 1,
                    'Air conditioner' => 1, 'AC remote' => 0, 'Pillow' => 4, 'Pillow case' => 4, 'Standing fan' => 1, 'Hanger' => 4,
                ],
                'Kitchen' => [
                    'Lighter' => 1, 'Tissue holder' => 1, 'Kitchen knife' => 5, 'Plate rack' => 1, 'Scissors' => 1, 'Jug' => 1,
                    'Tea cup' => 3, 'Fork' => 3, 'Spoon' => 6, 'Knife' => 8, 'Fan' => 2, 'Skyrun fridge' => 1, 'Hand towel' => 2,
                    'Microwave' => 1, 'Blender' => 2, 'Filter' => 2, 'Wine opener' => 1, 'Cooking pot' => 2, 'Frying pan' => 1,
                    'Grater' => 1, 'Bowl' => 3, 'Gas cooker (2-in-1)' => 1, 'Gas cylinder' => 1, 'Waste bin' => 1, 'Glass cup' => 4,
                    'Turning stick' => 1, 'Hook' => 1, 'Basket for liquid soap & sponge' => 1, 'Extractor' => 1,
                ],
            ],
        ];
    }

    /**
     * Daily inventory log, 5–12 Nov 2026.
     * [date, type, from, to, [item => qty], note?, staff?]
     */
    private function logEntries(): array
    {
        $queenBed = $this->suiteKey('Queen Suite', 'Bedroom');
        $kingKitchen = $this->suiteKey('King Suite', 'Kitchen');

        return [
            // 5 Nov — decor put out around the property
            ['2026-11-05', 'transfer', 'store', 'common', ['Small basket for flowers' => 6, 'Air freshener' => 2, 'Terracotta pot' => 6, 'Black vase pot' => 2, 'Quartz clock' => 4], 'Put out around the property'],
            ['2026-11-05', 'consume', 'store', null, ['"Mind your step" sticker' => 20], 'Stuck up around the property'],
            ['2026-11-05', 'receive', null, 'kitchen', ['Gas hose' => 1], 'Gas hose fitted'],

            // 6 Nov — kitchen set up from the store
            ['2026-11-06', 'transfer', 'store', 'kitchen', [
                'Glass cup' => 6, 'Sponge' => 5, 'Super cleaner sponge' => 6, 'Kitchen rag' => 8, 'Cooking pot' => 4,
                'Frying pan (black)' => 2, 'Frying pan (white)' => 1, 'Blender' => 1, 'Knife' => 1, 'Lighter' => 1, 'Opener' => 1,
                'Turning stick' => 1, 'Kitchen knife' => 5, 'Grater' => 1, 'Rubber bowl (blue & orange)' => 3,
            ], 'Moved to the kitchen'],
            ['2026-11-06', 'transfer', $queenBed, 'laundry', ['Duvet' => 1, 'Bedspread' => 1, 'Pillow case' => 3], 'Queen Suite linen sent for washing'],
            ['2026-11-06', 'transfer', 'store', 'kitchen', ['Gas pipe' => 1], 'Gas pipe carried from the store, 1 left'],
            ['2026-11-06', 'receive', null, 'store', [
                'Microwave' => 1, 'Gas cooker (2-in-1)' => 1, 'Sandwich toaster' => 1, 'Tea spoon' => 18, 'Non-stick frying pan' => 1,
                'Toilet block' => 5, 'Big tissue roll' => 2, 'Bread toaster' => 1, 'Quaker oats' => 1, 'Spoon' => 12, 'Fork' => 12,
                'Magic wrap' => 1, 'Tea cup' => 12, 'Saucer' => 12, 'Small rubber flower pot' => 6, 'Medium rubber flower pot' => 6,
                'Big flower vase' => 6, 'Biggest flower vase' => 6,
            ], 'New stock 6/11/2026'],

            // 7 Nov
            ['2026-11-07', 'consume', 'store', null, ['Duvet (4/6)' => 1], 'Duvet set carried out, one pack of 4/6 left', 'marvellous'],
            ['2026-11-07', 'consume', 'store', null, ['Bedspread' => 3], 'Bedspreads and cases carried out', 'marvellous'],
            ['2026-11-07', 'transfer', 'store', 'common', ['Streamer flowers' => 4, 'Gold & white little flowers' => 5], 'Flowers carried out for decoration'],
            ['2026-11-07', 'transfer', 'store', 'common', ['Small rubber flower pot' => 6, 'Medium rubber flower pot' => 6, 'Big flower vase' => 6, 'Biggest flower vase' => 1], 'Flower vases (red and black) carried out, 5 left'],
            ['2026-11-07', 'consume', 'store', null, ['Frame' => 2, 'Foot mat flower' => 2], "Returned to Mr Imoh's car"],
            ['2026-11-07', 'consume', 'common', null, ['Quartz clock' => 4], 'Clocks taken out'],
            ['2026-11-07', 'consume', 'store', null, ['Banana flower' => 4], 'Used'],
            ['2026-11-07', 'receive', null, 'store', [
                'Clothes peg' => 2, 'Plastic hanger' => 24, 'Wire hanger' => 24, 'Rope' => 1, 'Bucket' => 1, 'Basin' => 2,
                'Big basket' => 1, 'Plastic plate' => 100,
            ], 'New stock 7/11/2026'],
            ['2026-11-07', 'consume', 'store', null, ['Rope' => 1], 'Rope carried out', 'emma'],

            // 9 Nov
            ['2026-11-09', 'receive', null, 'store', [
                'Umbrella' => 4, 'Aluminium pot' => 3, 'Rubber bowl' => 5, 'Filter' => 1, 'Nose mask' => 2, 'Bowl plate (white)' => 8,
                'Flat plate' => 12, 'Tumbler glass' => 6, 'Saucer' => 9, 'Tea cup' => 6, 'Rubber tray (peach)' => 3, 'Beer cup' => 5,
                'Lamp' => 1, 'Cooking spoon' => 1, 'Slotted spoon & egg poacher' => 1, 'Rug' => 1, 'Plaster' => 1, 'Rubber hanger' => 7,
                'Iron hanger' => 4, 'Snowsea fridge' => 1, 'Frame' => 1,
            ], 'New stock 9/11/2026'],
            ['2026-11-09', 'transfer', 'store', 'madam', ['Umbrella' => 2], 'Umbrellas carried to Madam\'s room, 2 left'],
            ['2026-11-09', 'transfer', 'store', 'laundry', ['Stapling gun' => 1], 'Given to Ekerette', 'ekerette'],
            ['2026-11-09', 'transfer', 'laundry', 'store', ['Stapling gun' => 1], 'Returned', 'ekerette'],
            ['2026-11-09', 'transfer', 'store', 'laundry', ['Sponge' => 1], 'Carried out by the cleaner', 'marvellous'],
            ['2026-11-09', 'transfer', 'laundry', 'store', ['Sponge' => 1], 'Returned', 'marvellous'],
            ['2026-11-09', 'consume', 'store', null, ['File' => 3], 'Files carried out', 'emma'],
            ['2026-11-09', 'transfer', 'store', 'laundry', ['Iron' => 1], 'Industrial iron carried to the laundry'],
            ['2026-11-09', 'transfer', 'laundry', 'store', ['Iron' => 1], 'Returned'],
            ['2026-11-09', 'receive', null, 'store', ['TV remote' => 1, 'TV stand' => 2], 'Brought in from one of the rooms'],
            ['2026-11-09', 'transfer', 'store', 'reception', [
                'Book: The Female Daily' => 1, 'Book: Beyond a Sojourner' => 1, 'Book: Success All the Way' => 1,
                'Book: The Mysteries of the Strength of a Woman' => 1,
            ], 'Books moved to the reception'],
            ['2026-11-09', 'transfer', 'laundry', 'store', ['Duvet (6/6)' => 1, 'Bedspread' => 1, 'Pillow case' => 2], 'Complete duvet set returned to the store', 'laundry'],
            ['2026-11-09', 'transfer', 'store', $kingKitchen, ['Plastic plate' => 5], 'Taken to King Suite, 95 left'],
            ['2026-11-09', 'transfer', 'store', 'staff_room', ['Bedspread' => 1], 'Bedspread for the staff room'],
            ['2026-11-09', 'transfer', 'store', 'boys_room', ['Bedspread' => 1], "Bedspread for the boys' room"],
            ['2026-11-09', 'transfer', 'store', 'girls_room', ['Bedspread' => 1], "Bedspread for the girls' room"],

            // 10 Nov
            ['2026-11-10', 'transfer', 'store', 'kitchen', [
                'Aluminium pot' => 3, 'Rubber bowl' => 2, 'Filter' => 1, 'Bowl plate (white)' => 8, 'Flat plate' => 6, 'Saucer' => 9,
                'Rubber tray (peach)' => 3, 'Plastic plate' => 10, 'Slotted spoon & egg poacher' => 1, 'Cooking spoon' => 1,
                'Tumbler glass' => 6,
            ], 'Moved to the kitchen'],
            ['2026-11-10', 'consume', 'store', null, ['Liquid soap' => 3], 'Sent to the kitchen, security and the cleaners'],
            ['2026-11-10', 'consume', 'store', null, ['Toilet block' => 1], 'Carried out'],
            ['2026-11-10', 'receive', null, 'store', ['Tissue paper' => 4, 'Camphor' => 3, 'Bucket' => 1, 'Scooper' => 1], 'New stock 10/11/2026'],

            // 11 Nov
            ['2026-11-11', 'consume', 'store', null, ['Tooth brush' => 1, 'Bar soap' => 1, 'Bath gel' => 1, 'Shampoo' => 1, 'Body lotion' => 1, 'Toilet roll' => 2], 'Guest supplies issued'],
            ['2026-11-11', 'transfer', 'store', 'laundry', ['Rug' => 3], 'Rugs carried out by the laundry guy', 'laundry'],
            ['2026-11-11', 'transfer', 'store', 'laundry', ['Liquid soap' => 1, 'Plastic hanger' => 1], 'Given to the laundry guy', 'laundry'],
            ['2026-11-11', 'transfer', 'store', 'reception', ['Printer' => 1], 'Carried out for scanning'],
            ['2026-11-11', 'receive', null, 'store', ['Gas cylinder' => 1, 'Gas regulator' => 1, 'Gas hose' => 1], 'New stock 11/11/2026'],
            ['2026-11-11', 'consume', 'store', null, ['Light bulb' => 2], 'Used by the electrician'],
            ['2026-11-11', 'transfer', 'store', 'kitchen', ['Spoon' => 1], 'Moved to the kitchen'],
            ['2026-11-11', 'receive', null, 'kitchen', [
                'Bay leaf' => 14, 'Three Crowns milk' => 20, 'Nescafé 3-in-1' => 10, 'Sugar cubes' => 1, 'Lipton tea' => 1,
                'Bama mayonnaise' => 1, 'Milo' => 10, 'Nescafé (small)' => 20, 'Seasoning cubes' => 1, "King's vegetable oil" => 1,
                'Pepper, onions & tomatoes' => 1, 'Kitchen hanger' => 1, 'Big waste bin' => 1,
            ], 'Kitchen supplies'],
            ['2026-11-11', 'receive', null, 'store', ['Detergent' => 1, 'Baby & Me soap' => 2], 'New stock'],

            // 12 Nov
            ['2026-11-12', 'receive', null, 'store', ['X-Force security lock' => 4], 'New stock 12/11/2026'],
        ];
    }
}
