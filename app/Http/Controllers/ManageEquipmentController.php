<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Reservation;
use App\Models\User;
use App\Support\StockCheck;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Phase 8: lab staff and admins manage their department's equipment: categories, items, how many
 * there are (stock) and how many one booking may ask for. Turning an item off keeps old bookings.
 * Lowering the stock is allowed; bookings that now ask for more than there is are listed.
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

    public function index(Request $request): View
    {
        return view('manage.equipment', ['categories' => $this->categories($request->user())]);
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
        return EquipmentCategory::with(['equipment' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])
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
