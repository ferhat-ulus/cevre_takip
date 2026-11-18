<?php

// 2026 Çevre, Şehircilik ve İklim Değişikliği Bakanlığı birim fiyat listesi.
// Ücretler bu ortak katalogda tutulur; tesis detay ekranında gösterilmez.
$fee = static function (float $net, float $vat, float $gross, int $row): array {
    return ['net' => $net, 'vat' => $vat, 'gross' => $gross, 'source_row' => $row];
};

$topic = static function (string $key, string $group, string $service, int $incomeCode, array $fees, int $row): array {
    return [
        'key' => $key,
        'group' => $group,
        'service' => $service,
        'label' => $group === $service ? $group : $group.' — '.$service,
        'income_code' => $incomeCode,
        'fees' => $fees,
        'source_rows' => array_values(array_unique(array_column($fees, 'source_row'))),
    ];
};

$standard = static fn (float $net, float $vat, float $gross, int $row): array => ['standard' => $fee($net, $vat, $gross, $row)];
$annexed = static fn (float $net1, float $vat1, float $gross1, int $row1, float $net2, float $vat2, float $gross2, int $row2): array => [
    'ek1' => $fee($net1, $vat1, $gross1, $row1),
    'ek2' => $fee($net2, $vat2, $gross2, $row2),
];

return [
    'year' => 2026,
    'currency' => 'TRY',
    'vat_rate' => 20,
    'service_completion' => 'Belge bedeli yatırıldıktan 15 gün sonra',
    'eligible_person' => 'Gerçek/Tüzel Kişi',
    'documents' => [
        'gfb' => [
            'key' => 'gfb',
            'label' => 'Geçici Faaliyet Belgesi (GFB)',
            'income_code' => 106,
            'fees' => $annexed(33041.67, 6608.33, 39650, 6, 11250, 2250, 13500, 7),
        ],
    ],
    'permits' => [
        $topic('air_emission', 'Çevre İzni', 'Hava Emisyonu', 106, $annexed(89416.67, 17883.33, 107300, 9, 33041.67, 6608.33, 39650, 10), 9),
        $topic('noise_control', 'Çevre İzni', 'Gürültü Kontrolü', 106, $annexed(70000, 14000, 84000, 11, 28875, 5775, 34650, 12), 11),
        $topic('wastewater_discharge', 'Çevre İzni', 'Atıksu Deşarjı', 106, $annexed(70000, 14000, 84000, 13, 28875, 5775, 34650, 14), 13),
        $topic('deep_sea_discharge', 'Çevre İzni', 'Derin Deniz Deşarjı', 106, $annexed(70000, 14000, 84000, 15, 28875, 5775, 34650, 16), 15),
    ],
    'licenses' => [
        $topic('recovery_hazardous_waste', 'Geri Kazanım', 'Tehlikeli Atık', 105, $standard(82083.33, 16416.67, 98500, 21), 21),
        $topic('recovery_non_hazardous_waste', 'Geri Kazanım', 'Tehlikesiz Atık', 105, $annexed(82083.33, 16416.67, 98500, 22, 41833.33, 8366.67, 50200, 23), 22),
        $topic('recovery_waste_oil_refining', 'Geri Kazanım', 'Atık Yağ Rafinasyonu', 105, $standard(289666.67, 57933.33, 347600, 24), 24),
        $topic('recovery_vegetable_waste_oil', 'Geri Kazanım', 'Bitkisel Atık Yağ', 105, $standard(82083.33, 16416.67, 98500, 25), 25),
        $topic('recovery_batteries', 'Geri Kazanım', 'Atık Pil ve Akümülatör', 105, $standard(82083.33, 16416.67, 98500, 26), 26),
        $topic('recovery_end_of_life_tires', 'Geri Kazanım', 'Ömrünü Tamamlamış Lastik', 105, $standard(82083.33, 16416.67, 98500, 27), 27),

        $topic('disposal_incineration', 'Bertaraf', 'Atık Yakma ve Beraber Yakma', 105, $standard(289666.67, 57933.33, 347600, 29), 29),
        $topic('disposal_advanced_thermal', 'Bertaraf', 'İleri Termal İşlem Tesisleri (Piroliz, Gazlaştırma)', 105, $standard(289666.67, 57933.33, 347600, 30), 30),
        $topic('disposal_landfill_class_1', 'Bertaraf', 'Düzenli Depolama 1. Sınıf', 105, $standard(289666.67, 57933.33, 347600, 31), 31),
        $topic('disposal_landfill_class_2', 'Bertaraf', 'Düzenli Depolama 2. Sınıf', 105, $standard(82083.33, 16416.67, 98500, 32), 32),
        $topic('disposal_landfill_class_3', 'Bertaraf', 'Düzenli Depolama 3. Sınıf', 105, $standard(82083.33, 16416.67, 98500, 33), 33),
        $topic('disposal_mining_waste_category_a', 'Bertaraf', 'Maden Atığı Bertaraf — Kategori A', 105, $standard(289666.67, 57933.33, 347600, 34), 34),
        $topic('disposal_mining_waste_category_b', 'Bertaraf', 'Maden Atığı Bertaraf — Kategori B', 105, $standard(82083.33, 16416.67, 98500, 35), 35),

        $topic('storage_general', 'Ara Depolama', 'Ara Depolama', 105, $standard(289666.67, 57933.33, 347600, 37), 37),
        $topic('storage_vegetable_waste_oil', 'Ara Depolama', 'Bitkisel Atık Yağ Ara Depolama Tesisi', 105, $annexed(46541.67, 9308.33, 55850, 38, 25750, 5150, 30900, 39), 38),
        $topic('storage_waste_oil_transfer', 'Ara Depolama', 'Atık Yağ Transfer Noktası', 105, $annexed(46541.67, 9308.33, 55850, 40, 25750, 5150, 30900, 41), 40),
        $topic('storage_weee_transfer', 'Ara Depolama', 'Atık Elektrikli ve Elektronik Eşya Transfer Noktaları', 105, $annexed(46541.67, 9308.33, 55850, 42, 25750, 5150, 30900, 43), 42),

        $topic('processing_medical_waste_sterilization', 'İşleme', 'Tıbbi Atık Sterilizasyon', 105, $standard(82083.33, 16416.67, 98500, 45), 45),
        $topic('processing_end_of_life_vehicle_storage', 'İşleme', 'Ömrünü Tamamlamış Araç Geçici Depolama', 105, $standard(57500, 11500, 69000, 46), 46),
        $topic('processing_scrap_metal_and_vehicles', 'İşleme', 'Hurda Metal / Ömrünü Tamamlamış Araç İşleme', 105, $annexed(289666.67, 57933.33, 347600, 47, 156875, 31375, 188250, 48), 47),
        $topic('processing_weee', 'İşleme', 'Atık Elektrikli ve Elektronik Eşya İşleme', 105, $standard(77416.67, 15483.33, 92900, 49), 49),
        $topic('processing_tanker_cleaning', 'İşleme', 'Tanker Temizleme', 105, $annexed(82083.33, 16416.67, 98500, 50, 58833.33, 11766.67, 70600, 51), 50),
        $topic('processing_collection_sorting', 'İşleme', 'Toplama ve Ayırma', 105, $annexed(71125, 14225, 85350, 52, 41833.33, 8366.67, 50200, 53), 52),
        $topic('processing_rdf_preparation', 'İşleme', 'Atıktan Türetilmiş Yakıt (ATY) Hazırlama Tesisi', 105, $standard(82083.33, 16416.67, 98500, 54), 54),
        $topic('processing_ship_recycling', 'İşleme', 'Gemi Geri Dönüşüm Tesisi', 105, $standard(289666.67, 57933.33, 347600, 55), 55),
        $topic('processing_waste_acceptance', 'İşleme', 'Atık Kabul Tesisi', 105, $annexed(111375, 22275, 133650, 56, 62750, 12550, 75300, 57), 56),
        $topic('processing_biodegradable_waste', 'İşleme', 'Biyobozunur Atık İşletme', 105, $standard(82083.33, 16416.67, 98500, 58), 58),
        $topic('processing_hazardous_pre_treatment', 'İşleme', 'Tehlikeli Atık Ön İşlem Tesisi', 105, $standard(82083.33, 16416.67, 98500, 59), 59),
        $topic('processing_non_hazardous_pre_treatment', 'İşleme', 'Tehlikesiz Atık Ön İşlem Tesisi', 105, $standard(82083.33, 16416.67, 98500, 60), 60),
        $topic('processing_preparing_for_reuse', 'İşleme', 'Yeniden Kullanıma Hazırlama', 105, $standard(41833.33, 8366.67, 50200, 61), 61),
        $topic('processing_weee_preparing_for_reuse', 'İşleme', 'Atık Elektrikli ve Elektronik Eşya Yeniden Kullanıma Hazırlama Tesisi', 105, $standard(41833.33, 8366.67, 50200, 62), 62),

        $topic('purification_pcb', 'Arındırma', 'PCB Arındırma', 105, $standard(82083.33, 16416.67, 98500, 64), 64),
    ],
    'excluded_services' => [
        ['service' => 'Çevre İzni/Çevre İzin ve Lisansı Belgesinin Güncellenmesi', 'source_rows' => [17, 18], 'reason' => 'Kullanıcı talebiyle bu aşamada kapsam dışı bırakıldı.'],
    ],
];
