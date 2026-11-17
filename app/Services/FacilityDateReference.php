<?php

namespace App\Services;

use App\Models\Firm;

class FacilityDateReference
{
    public static function key(string $name): string
    {
        return preg_replace('/[^\p{L}\p{N}]/u', '', str_replace('İ', 'I', mb_strtoupper($name, 'UTF-8')));
    }

    public static function load(): array
    {
        $path = storage_path('app/private/facility-date-reference.csv');
        if (!is_file($path)) {
            return [];
        }
        $file = fopen($path, 'rb');
        if (!$file) {
            throw new \RuntimeException('Tesis tarihleri listesi okunamadı.');
        }
        $references = [];
        try {
            fgetcsv($file, 0, ';', '"', '');
            while (($row = fgetcsv($file, 0, ';', '"', '')) !== false) {
                if (count($row) !== 5 || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $row[3])
                    || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $row[4])) {
                    throw new \RuntimeException('Tesis tarihleri listesinde geçersiz satır.');
                }
                $references[self::key($row[1])][] = ['serviceStartedAt' => $row[3], 'contractDate' => $row[4]];
            }
        } finally {
            fclose($file);
        }

        return $references;
    }

    public static function fillOnCreate(Firm $firm): void
    {
        $key = self::key($firm->data['name'] ?? '');
        $matches = self::load()[$key] ?? [];
        if (count($matches) !== 1 || Firm::query()->pluck('data')->contains(function ($data) use ($key) {
            $data = is_array($data) ? $data : json_decode($data, true);

            return self::key($data['name'] ?? '') === $key;
        })) {
            return;
        }
        $data = $firm->data;
        foreach ($matches[0] as $field => $date) {
            if (empty($data[$field])) {
                $data[$field] = $date;
            }
        }
        $firm->data = $data;
    }
}
