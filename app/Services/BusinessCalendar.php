<?php

namespace App\Services;

use App\Models\CalendarHoliday;
use Carbon\CarbonInterface;
use Carbon\CarbonImmutable;

class BusinessCalendar
{
    public const TIMEZONE = 'Europe/Istanbul';

    private const REQUIRED_RELIGIOUS_HOLIDAYS = 7;

    /** @return array<int, array{date: string, title: string, type: string, managed: bool}> */
    public function entriesForYear(int $year): array
    {
        $fixed = collect($this->fixedHolidaysForYear($year));
        $religious = CalendarHoliday::query()
            ->whereYear('holiday_date', $year)
            ->orderBy('holiday_date')
            ->get()
            ->map(fn (CalendarHoliday $holiday) => [
                'id' => $holiday->id,
                'date' => $holiday->holiday_date->toDateString(),
                'title' => $holiday->title,
                'type' => $holiday->type,
                'managed' => true,
                'verified_at' => $holiday->verified_at?->toIso8601String(),
            ]);

        return $fixed->merge($religious)->sortBy('date')->values()->all();
    }

    /** @return array<int, string> */
    public function blockedDates(int $fromYear, int $toYear): array
    {
        $fixed = collect(range($fromYear, $toYear))
            ->flatMap(fn (int $year) => collect($this->fixedHolidaysForYear($year))->pluck('date'));

        $religious = CalendarHoliday::query()
            ->whereBetween('holiday_date', ["{$fromYear}-01-01", "{$toYear}-12-31"])
            ->pluck('holiday_date')
            ->map(fn ($date) => CarbonImmutable::parse($date, self::TIMEZONE)->toDateString());

        return $fixed->merge($religious)->unique()->sort()->values()->all();
    }

    public function isBlockedDate(string|CarbonInterface $date): bool
    {
        $date = $date instanceof CarbonInterface
            ? $date->setTimezone(self::TIMEZONE)->toDateString()
            : CarbonImmutable::parse($date, self::TIMEZONE)->toDateString();

        if (in_array($date, collect($this->fixedHolidaysForYear((int) substr($date, 0, 4)))->pluck('date')->all(), true)) {
            return true;
        }

        return CalendarHoliday::query()->whereDate('holiday_date', $date)->exists();
    }

    /** @return array{visible: bool, year: int, remaining: int, message: string} */
    public function reminder(): array
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $year = $now->year + 1;
        $count = CalendarHoliday::query()
            ->where('type', 'religious')
            ->whereYear('holiday_date', $year)
            ->count();
        $remaining = max(0, self::REQUIRED_RELIGIOUS_HOLIDAYS - $count);

        return [
            'visible' => $remaining > 0,
            'year' => $year,
            'remaining' => $remaining,
            'message' => $remaining > 0
                ? "{$year} yılı için Ramazan ve Kurban Bayramı tam gün tarihleri henüz tamamlanmadı. Diyanet takvimi yayımlandığında {$remaining} günü ekleyin."
                : "{$year} yılı dini bayram tatil takvimi tamamlandı.",
        ];
    }

    /** @return array<int, array{date: string, title: string, type: string, managed: bool}> */
    private function fixedHolidaysForYear(int $year): array
    {
        return [
            ['date' => "{$year}-01-01", 'title' => 'Yılbaşı', 'type' => 'fixed', 'managed' => false],
            ['date' => "{$year}-04-23", 'title' => '23 Nisan Ulusal Egemenlik ve Çocuk Bayramı', 'type' => 'fixed', 'managed' => false],
            ['date' => "{$year}-05-01", 'title' => '1 Mayıs Emek ve Dayanışma Günü', 'type' => 'fixed', 'managed' => false],
            ['date' => "{$year}-05-19", 'title' => '19 Mayıs Atatürk’ü Anma, Gençlik ve Spor Bayramı', 'type' => 'fixed', 'managed' => false],
            ['date' => "{$year}-07-15", 'title' => '15 Temmuz Demokrasi ve Millî Birlik Günü', 'type' => 'fixed', 'managed' => false],
            ['date' => "{$year}-08-30", 'title' => '30 Ağustos Zafer Bayramı', 'type' => 'fixed', 'managed' => false],
            ['date' => "{$year}-10-29", 'title' => '29 Ekim Cumhuriyet Bayramı', 'type' => 'fixed', 'managed' => false],
        ];
    }
}
