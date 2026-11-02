<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Phase 8: lab staff and admins manage their department's rooms: add one, edit its seats and
 * notes, or turn it off (it can't be booked anymore, but old bookings keep it). Rooms are
 * shared between departments, so a change shows for every department that uses the room.
 */
class ManageRoomsController extends Controller
{
    private const MESSAGES = [
        'name.required' => 'Give the room a name, e.g. CEA300.',
        'name.max' => 'Keep the name under 30 characters.',
        'name.unique' => 'Another room already has that name.',
        'capacity.*' => 'Seats must be a whole number from 0 to 1000.',
        'building.max' => 'Keep the building under 60 characters.',
        'note.max' => 'Keep the note under 200 characters.',
    ];

    public function index(Request $request): View
    {
        $department = $request->user()->department;

        return view('manage.rooms', [
            'department' => $department,
            'rooms' => $department->rooms()->withCount('departments')->orderBy('name')->get(),
        ]);
    }

    /** Add a room to the department. A room another department already has is shared, not copied. */
    public function store(Request $request): RedirectResponse
    {
        $department = $request->user()->department;
        $data = $request->validateWithBag('add', [
            'name' => ['required', 'string', 'max:30'],
            'building' => ['nullable', 'string', 'max:60'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'note' => ['nullable', 'string', 'max:200'],
        ], self::MESSAGES);

        $name = trim($data['name']);
        $room = Room::where('name', $name)->first();
        if ($room && $department->rooms()->whereKey($room->id)->exists()) {
            return back()->withErrors(['name' => $room->name.' is already on your list.'], 'add')->withInput();
        }

        if ($room) {
            $department->rooms()->attach($room->id);
            $message = $room->name.' was already used by another department. It’s on your list now too.';
        } else {
            $room = Room::create([
                'name' => $name,
                'building' => $data['building'] ?? null,
                'capacity' => $data['capacity'] ?? null,
                'fixed_equipment_note' => $data['note'] ?? null,
                'is_active' => true,
            ]);
            $department->rooms()->attach($room->id);
            $message = 'Added '.$room->name.'. It can be booked now.';
        }

        return redirect()->route('manage.rooms')->with('status', $message);
    }

    public function edit(Request $request, Room $room): View
    {
        $user = $request->user();
        $this->checkDepartment($user, $room);

        return view('manage.room_edit', [
            'room' => $room,
            'sharedWith' => $room->departments()->where('departments.id', '!=', $user->department_id)->pluck('name'),
            'upcoming' => $this->upcoming($room),
        ]);
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $this->checkDepartment($request->user(), $room);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:30', Rule::unique('rooms', 'name')->ignore($room->id)],
            'building' => ['nullable', 'string', 'max:60'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'note' => ['nullable', 'string', 'max:200'],
        ], self::MESSAGES);

        $wasActive = $room->is_active;
        $room->update([
            'name' => trim($data['name']),
            'building' => $data['building'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'fixed_equipment_note' => $data['note'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $count = $this->upcoming($room)->count();
        $message = match (true) {
            $wasActive && ! $room->is_active => $room->name.' is turned off: it can’t be booked anymore.'
                .($count === 1 ? ' It still has 1 upcoming booking, listed below. Move or cancel it.' : '')
                .($count > 1 ? ' It still has '.$count.' upcoming bookings, listed below. Move or cancel them.' : ''),
            ! $wasActive && $room->is_active => $room->name.' is turned on: it can be booked again.',
            default => 'Saved '.$room->name.'.',
        };

        return redirect()->route('manage.rooms.edit', $room)->with('status', $message);
    }

    /** Bookings in this room that haven't ended and still hold it */
    private function upcoming(Room $room)
    {
        return Reservation::with(['user.section'])
            ->blocking()
            ->where('room_id', $room->id)
            ->where('end_time', '>', now())
            ->orderBy('start_time')
            ->get();
    }

    /** Only rooms on the user's department's list */
    private function checkDepartment(User $user, Room $room): void
    {
        abort_unless($user->department->rooms()->whereKey($room->id)->exists(), 404);
    }
}
