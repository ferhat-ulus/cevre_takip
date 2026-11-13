<?php

namespace Tests\Feature;

use App\Models\CalendarHoliday;
use App\Services\BusinessCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_holidays_are_generated_without_admin_data(): void
    {
        $calendar = app(BusinessCalendar::class);

        $this->assertTrue($calendar->isBlockedDate('2030-10-29'));
        $this->assertTrue($calendar->isBlockedDate('2030-05-01'));
        $this->assertFalse($calendar->isBlockedDate('2030-10-28'));
    }

    public function test_only_full_day_religious_holidays_block_calendar_dates(): void
    {
        $calendar = app(BusinessCalendar::class);

        $this->assertFalse($calendar->isBlockedDate('2026-03-19'));
        $this->assertTrue($calendar->isBlockedDate('2026-03-20'));
        $this->assertTrue($calendar->isBlockedDate('2026-05-27'));
    }

    public function test_religious_holiday_can_share_a_date_with_a_fixed_holiday(): void
    {
        CalendarHoliday::create([
            'holiday_date' => '2027-05-19',
            'title' => 'Kurban Bayramı 1. Gün',
            'type' => 'religious',
        ]);

        $calendar = app(BusinessCalendar::class);

        $this->assertTrue($calendar->isBlockedDate('2027-05-19'));
        $this->assertSame(1, CalendarHoliday::query()->whereDate('holiday_date', '2027-05-19')->count());
        $this->assertTrue(collect($calendar->entriesForYear(2027))
            ->where('date', '2027-05-19')
            ->contains('title', 'Kurban Bayramı 1. Gün'));
    }

    public function test_next_year_reminder_clears_after_seven_religious_days_are_entered(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-13 10:00:00', BusinessCalendar::TIMEZONE));
        $calendar = app(BusinessCalendar::class);

        $this->assertTrue($calendar->reminder()['visible']);
        $this->assertSame(2027, $calendar->reminder()['year']);

        foreach (range(1, 7) as $day) {
            CalendarHoliday::create([
                'holiday_date' => sprintf('2027-03-%02d', $day),
                'title' => "Test tatili {$day}",
                'type' => 'religious',
            ]);
        }

        $this->assertFalse($calendar->reminder()['visible']);
        CarbonImmutable::setTestNow();
    }

    public function test_application_uses_istanbul_as_its_business_timezone(): void
    {
        $this->assertSame(BusinessCalendar::TIMEZONE, config('app.timezone'));
    }
}
