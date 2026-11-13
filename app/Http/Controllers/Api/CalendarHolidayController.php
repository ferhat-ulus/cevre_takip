<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\CalendarHoliday;
use App\Services\BusinessCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarHolidayController extends Controller
{
    public function __construct(private readonly BusinessCalendar $calendar)
    {
    }

    public function index(): Response
    {
        $year = CarbonImmutable::now(BusinessCalendar::TIMEZONE)->year;

        return Inertia::render('TatilYonetimi', [
            'initialYear' => $year,
            'calendar' => $this->calendarPayload($year),
            'reminder' => $this->calendar->reminder(),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $year = (int) $request->integer('year', CarbonImmutable::now(BusinessCalendar::TIMEZONE)->year);

        abort_unless($year >= 2020 && $year <= 2100, 422, 'Geçersiz yıl.');

        return response()->json($this->calendarPayload($year));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'holiday_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:2100-12-31'],
            'title' => ['required', 'string', 'max:120'],
        ]);

        $date = CarbonImmutable::parse($validated['holiday_date'], BusinessCalendar::TIMEZONE);
        $title = trim($validated['title']);

        if (CalendarHoliday::query()
            ->whereDate('holiday_date', $validated['holiday_date'])
            ->where('title', $title)
            ->exists()) {
            return response()->json(['error' => 'Bu tarihte aynı ada sahip bir tatil kaydı zaten var.'], 422);
        }

        $holiday = CalendarHoliday::create([
            'holiday_date' => $validated['holiday_date'],
            'title' => $title,
            'type' => 'religious',
            'created_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'calendar_holiday_created',
            'description' => "Resmî tatil eklendi: {$holiday->title} / {$validated['holiday_date']}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['ok' => true, 'calendar' => $this->calendarPayload($date->year)]);
    }

    public function destroy(Request $request, CalendarHoliday $calendarHoliday): JsonResponse
    {
        $year = $calendarHoliday->holiday_date->year;
        $description = "Resmî tatil silindi: {$calendarHoliday->title} / {$calendarHoliday->holiday_date->toDateString()}";
        $calendarHoliday->delete();

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'calendar_holiday_deleted',
            'description' => $description,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['ok' => true, 'calendar' => $this->calendarPayload($year)]);
    }

    /** @return array{year: int, entries: array<int, array<string, mixed>>} */
    private function calendarPayload(int $year): array
    {
        return [
            'year' => $year,
            'entries' => $this->calendar->entriesForYear($year),
        ];
    }
}
