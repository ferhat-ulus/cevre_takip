<?php

namespace App\Console\Commands;

use App\Models\Firm;
use App\Services\FacilityDateReference;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FillFacilityDates extends Command
{
    protected $signature = 'facilities:fill-dates {--apply : Save missing dates}';

    protected $description = 'Match facility reference dates by full facility name';

    public function handle(): int
    {
        $references = FacilityDateReference::load();
        if ($references === []) {
            $this->error('Tarih kaynağı bulunamadı.');

            return self::FAILURE;
        }
        $apply = $this->option('apply');
        $counts = DB::transaction(function () use ($references, $apply) {
            $query = Firm::query()->orderBy('id');
            $firms = ($apply ? $query->lockForUpdate() : $query)->get()
                ->groupBy(fn (Firm $firm) => FacilityDateReference::key($firm->data['name'] ?? ''));
            $counts = ['missing' => 0, 'ambiguous' => 0, 'conflicts' => 0, 'fillable' => 0, 'current' => 0];
            foreach ($references as $key => $rows) {
                $matches = $firms->get($key, collect());
                if (count($rows) !== 1 || $matches->count() > 1) {
                    $counts['ambiguous'] += count($rows);
                    continue;
                }
                if ($matches->isEmpty()) {
                    $counts['missing']++;
                    continue;
                }
                $firm = $matches->first();
                $data = $firm->data;
                foreach ($rows[0] as $field => $date) {
                    if (!empty($data[$field]) && $data[$field] !== $date) {
                        $counts['conflicts']++;
                        continue 2;
                    }
                }
                $updated = array_merge($data, $rows[0]);
                if ($updated === $data) {
                    $counts['current']++;
                    continue;
                }
                $counts['fillable']++;
                if ($apply) {
                    $firm->data = $updated;
                    $firm->save();
                }
            }

            return $counts;
        });
        $this->line(json_encode($counts, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
