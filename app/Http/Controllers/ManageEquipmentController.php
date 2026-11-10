<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Reservation;
use App\Models\User;
use App\Support\ResourceDeletion;
use App\Support\StockCheck;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Phase 8: lab staff and admins manage their department's equipment: categories, items, how many
 * there are (stock) and how many one booking may ask for. Turning an item off keeps old bookings.
 * Lowering the stock is allowed; bookings that now ask for more than there is are listed. An item entered by mistake (on no
 * booking, ever) can be deleted; anything with history is turned off instead.
 */
class ManageEquipmentController extends Controller
{
    private const MESSAGES = [
        'name.required' => 'Give it a name.',
        'name.max' => 'Keep the name under 60 characters.',
        'name.unique' => 'That name is already used in this category.',
        'category_id.*' => 'Pick a category from the list.',
        'total_qty.*' => 'Stock must be a whole number from 0 to 9999.',
        'max_per_request.*' => 'The most per booking must be a whole number from 1 to 99.',
    ];

    /** Items shown per page */
    private const PER_PAGE = 12;

    /**
     * GET /manage/equipment?q=&page=
     * Items are paged on the server (12 a page, in category order, then item order), then grouped under their category.
     * ?q= matches an item or category name. Categories with no items yet are listed after the last page of items.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 60);
        $like = '%'.addcslashes($q, '\\%_').'%';

        $items = Equipment::query()
            ->join('equipment_categories as c', 'c.id', '=', 'equipment.category_id')
            ->where('c.department_id', $user->department_id)
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('equipment.name', 'like', $like)->orWhere('c.name', 'like', $like)))
            ->select('equipment.*')
            ->orderBy('c.sort_order')->orderBy('c.name')->orderBy('c.id')
            ->orderBy('equipment.sort_order')->orderBy('equipment.name')->orderBy('equipment.id');

        $lastPage = max(1, (int) ceil((clone $items)->count() / self::PER_PAGE));
        $page = min(max(1, (int) $request->query('page', 1)), $lastPage);
        $pager = $items->withCount('reservations')->with('category')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page)->withQueryString();

        $categoryCounts = EquipmentCategory::where('department_id', $user->department_id)->withCount('equipment')->get()->keyBy('id');
        $groups = $pager->getCollection()->groupBy('category_id')->map(fn ($rows, $id) => ['category' => $categoryCounts[$id] ?? $rows->first()->category, 'items' => $rows])->values();
        $empty = $page === $lastPage
            ? $categoryCounts->filter(fn ($c) => $c->equipment_count === 0 && ($q === '' || mb_stripos($c->name, $q) !== false))->sortBy([['sort_order', 'asc'], ['name', 'asc']])->values()
            : collect();

        return view('manage.equipment', [
            'groups' => $groups,
            'emptyCategories' => $empty,
            'pager' => $pager,
            'q' => $q,
            'categories' => $categoryCounts->sortBy([['sort_order', 'asc'], ['name', 'asc']])->values(),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validateWithBag('category', [
            'name' => ['required', 'string', 'max:60',
                Rule::unique('equipment_categories', 'name')->where('department_id', $user->department_id)],
        ], ['name.unique' => 'You already have a category with that name.'] + self::MESSAGES);

        $category = EquipmentCategory::create([
            'department_id' => $user->department_id,
            'name' => trim($data['name']),
            'sort_order' => (int) EquipmentCategory::where('department_id', $user->department_id)->max('sort_order') + 1,
        ]);

        return redirect()->route('manage.equipment')->with('status', 'Added the category '.$category->name.'.');
    }

    public function updateCategory(Request $request, EquipmentCategory $category): RedirectResponse
    {
        $user = $request->user();
        abort_unless($category->department_id === $user->department_id, 404);
        $data = $request->validateWithBag('category'.$category->id, [
            'name' => ['required', 'string', 'max:60',
                Rule::unique('equipment_categories', 'name')->where('department_id', $user->department_id)->ignore($category->id)],
        ], ['name.unique' => 'You already have a category with that name.'] + self::MESSAGES);

        $category->update(['name' => trim($data['name'])]);

        return redirect()->route('manage.equipment')->with('status', 'Renamed the category to '.$category->name.'.');
    }

    /** Add an item to one of the department's categories */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validateWithBag('item', [
            'category_id' => ['required', 'integer', Rule::exists('equipment_categories', 'id')->where('department_id', $user->department_id)],
            'name' => ['required', 'string', 'max:60', Rule::unique('equipment', 'name')->where('category_id', (int) $request->input('category_id'))],
            'total_qty' => ['required', 'integer', 'min:0', 'max:9999'],
            'max_per_request' => ['required', 'integer', 'min:1', 'max:99'],
        ], self::MESSAGES);

        $item = Equipment::create([
            'category_id' => $data['category_id'],
            'name' => trim($data['name']),
            'total_qty' => $data['total_qty'],
            'max_per_request' => $data['max_per_request'],
            'sort_order' => (int) Equipment::where('category_id', $data['category_id'])->max('sort_order') + 1,
            'is_active' => true,
        ]);

        return redirect()->route('manage.equipment')->with('status', 'Added '.$item->name.' ('.$item->total_qty.' in stock).');
    }

    public function edit(Request $request, Equipment $equipment): View
    {
        $user = $request->user();
        $this->checkDepartment($user, $equipment);
        [$upcoming, $over] = $this->upcoming($equipment);

        return view('manage.equipment_edit', [
            'item' => $equipment,
            'categories' => $this->categories($user),
            'upcoming' => $upcoming,
            'over' => $over,
            'deleteBlockers' => ResourceDeletion::equipmentBlockers($equipment),
        ]);
    }

    public function update(Request $request, Equipment $equipment): RedirectResponse
    {
        $user = $request->user();
        $this->checkDepartment($user, $equipment);
        $data = $request->validate([
            'category_id' => ['required', 'integer', Rule::exists('equipment_categories', 'id')->where('department_id', $user->department_id)],
            'name' => ['required', 'string', 'max:60',
                Rule::unique('equipment', 'name')->where('category_id', (int) $request->input('category_id'))->ignore($equipment->id)],
            'total_qty' => ['required', 'integer', 'min:0', 'max:9999'],
            'max_per_request' => ['required', 'integer', 'min:1', 'max:99'],
        ], self::MESSAGES);

        $wasActive = $equipment->is_active;
        $equipment->update([
            'category_id' => $data['category_id'],
            'name' => trim($data['name']),
            'total_qty' => $data['total_qty'],
            'max_per_request' => $data['max_per_request'],
            'is_active' => $request->boolean('is_active'),
        ]);

        [$upcoming, $over] = $this->upcoming($equipment);
        $message = match (true) {
            $wasActive && ! $equipment->is_active => $equipment->name.' is turned off: it can’t be booked anymore.',
            ! $wasActive && $equipment->is_active => $equipment->name.' is turned on: it can be booked again.',
            default => 'Saved '.$equipment->name.'.',
        };
        if ($over) {
            $message .= count($over) === 1
                ? ' 1 upcoming booking asks for more than you have now. It’s listed below: move or cancel it, or raise the stock.'
                : ' '.count($over).' upcoming bookings ask for more than you have now. They’re listed below: move or cancel them, or raise the stock.';
        }

        return redirect()->route('manage.equipment.edit', $equipment)->with('status', $message);
    }

    /**
     * Delete an item that is on no booking. Same roles and department check as editing; DELETE + CSRF only. The check runs again
     * inside the transaction with the item locked; the database's restrict foreign key is the last line of defence.
     * Categories and every booking's history are never touched.
     */
    public function destroy(Request $request, Equipment $equipment): RedirectResponse
    {
        $user = $request->user();
        $this->checkDepartment($user, $equipment);
        if (! $request->boolean('confirm')) {
            return $this->refuse($equipment, ['Tick the box to confirm that you want to delete '.$equipment->name.'.']);
        }

        $name = $equipment->name;
        try {
            $blockers = DB::transaction(function () use ($equipment) {
                $locked = Equipment::whereKey($equipment->id)->lockForUpdate()->first();
                if (! $locked) {
                    return null;   // already gone
                }
                $blockers = ResourceDeletion::equipmentBlockers($locked);
                if ($blockers === []) {
                    $locked->delete();
                }

                return $blockers;
            });
        } catch (QueryException $e) {
            // The database refused (a foreign key, a busy server, or something unexpected). Nothing was changed.
            return $this->refuse($equipment, [ResourceDeletion::messageForFailure($e, $name, ['equipment_id' => $equipment->id, 'user_id' => $request->user()->id])]);
        }

        if ($blockers === null) {
            return redirect()->route('manage.equipment')->with('status', $name.' was already deleted.');
        }
        if ($blockers !== []) {
            return $this->refuse($equipment, [...$blockers, ResourceDeletion::TURN_OFF_ADVICE]);
        }
        Log::info('Equipment deleted', ['item' => $name, 'by' => $user->id, 'department_id' => $user->department_id]);

        return redirect()->route('manage.equipment')->with('status', 'Deleted '.$name.'. It was on no booking, so no history was lost.');
    }

    private function refuse(Equipment $item, array $messages): RedirectResponse
    {
        return redirect()->route('manage.equipment.edit', $item)->withErrors($messages, 'delete');
    }

    /**
     * Bookings that include this item, haven't ended and still hold it, with how many each asks for
     * (->asked), and the ids of those that ask for more than the stock (StockCheck).
     */
    private function upcoming(Equipment $item): array
    {
        $bookings = Reservation::with(['user.section', 'room'])
            ->blocking()
            ->where('end_time', '>', now())
            ->whereHas('equipment', fn ($q) => $q->whereKey($item->id))
            ->orderBy('start_time')
            ->get();

        $asked = DB::table('reservation_equipment')
            ->where('equipment_id', $item->id)
            ->whereIn('reservation_id', $bookings->pluck('id'))
            ->pluck('qty_requested', 'reservation_id');
        $bookings->each(fn ($booking) => $booking->setAttribute('asked', (int) ($asked[$booking->id] ?? 0)));

        $over = StockCheck::overBooked($bookings->map(fn ($booking) => [
            'id' => $booking->id,
            'start' => $booking->start_time->timestamp,
            'end' => $booking->end_time->timestamp,
            'qty' => $booking->asked,
        ])->all(), $item->total_qty);

        return [$bookings, $over];
    }

    private function categories(User $user)
    {
        return EquipmentCategory::with(['equipment' => fn ($q) => $q->withCount('reservations')->orderBy('sort_order')->orderBy('name')])
            ->where('department_id', $user->department_id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** Only items in the user's department's categories */
    private function checkDepartment(User $user, Equipment $equipment): void
    {
        abort_unless($equipment->category?->department_id === $user->department_id, 404);
    }
}
