<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import PlatformLayout from '@/layouts/PlatformLayout.vue';

defineOptions({ layout: PlatformLayout });
const props = defineProps({ records: Array, history: Object, admin: Boolean, canWrite: Boolean, today: String, wasteCatalog: Object, adrEntegre: Object, canClassifyAdr: Boolean, adrCatalog: Object, adrUsers: Array, facilityView: String, statisticsView: Boolean });
const newFacility = ref({});
const chosenFacility = ref({});
const cardEditing = ref(false);
const facilityCard = useForm({ role: '', card_id: null, company_name: '', vkn: '', name: '', address: '', district: '', ckn: '', record_ids: [] });
function editFacilityCard(facility) {
    facilityCard.clearErrors();
    facilityCard.role = props.facilityView;
    facilityCard.card_id = facility.confirmed?.card_id ?? null;
    facilityCard.company_name = facility.confirmed?.company_name ?? '';
    facilityCard.vkn = /^\d{10}$/.test(facility.vkn) ? facility.vkn : '';
    facilityCard.name = facility.confirmed?.name ?? facility.label;
    facilityCard.address = facility.confirmed?.address ?? (facility.address.includes(' / ') ? '' : facility.address);
    facilityCard.district = facility.confirmed?.district ?? (facility.district.includes(' / ') ? '' : facility.district);
    facilityCard.ckn = facility.confirmed?.ckn ?? '';
    facilityCard.record_ids = [...facility.ids];
    cardEditing.value = true;
}
const facilitySearch = ref('');
const facilityTitle = computed(() => props.facilityView === 'producer' ? 'Atık Üreticisi Tesisler' : 'Atık İşleme Tesisleri');
const adrAccess = useForm({ user_id: '', enabled: true });
const adrForm = useForm({ un_number: '', waste_code: '', approve_existing: false, manual_entry: false,
    manual_adr_name: '', manual_adr_class: '', adr_reference: '' });
const defaultUnNumbers = ['3077', '3082'];
const showOtherUn = ref(false);
const otherCatalogEntries = computed(() => Object.values(props.adrCatalog).filter(entry => !defaultUnNumbers.includes(entry.un_number)));
const manualUnMissingDetails = computed(() => adrForm.manual_entry && !props.adrCatalog[adrForm.un_number]
    && (!/^\d{4}$/.test(adrForm.un_number) || !adrForm.manual_adr_name.trim() || !adrForm.manual_adr_class || !adrForm.adr_reference.trim()));
const adrRow = ref(null);
const copied = ref('');
function editAdr(record) {
    adrForm.clearErrors();
    adrForm.reset();
    adrRow.value = record.id;
    adrForm.waste_code = record.waste_code;
    adrForm.un_number = props.adrCatalog[record.un_number] || record.adr_reference ? record.un_number : '';
    showOtherUn.value = !!adrForm.un_number && !defaultUnNumbers.includes(adrForm.un_number);
    if (record.adr_reference && !props.adrCatalog[record.un_number]) {
        adrForm.manual_entry = true;
        adrForm.manual_adr_name = record.adr_name ?? '';
        adrForm.manual_adr_class = record.adr_class ?? '';
        adrForm.adr_reference = record.adr_reference;
    }
    adrForm.approve_existing = false;
}
function saveAdr(record) {
    adrForm.patch('/atik-takip/kayitlar/' + record.id + '/adr', {
        preserveScroll: true, onSuccess: () => { adrRow.value = null; },
    });
}
function approveAdr(record) {
    editAdr(record);
    adrForm.un_number = record.un_number;
    adrForm.approve_existing = true;
    saveAdr(record);
}
async function copyAdr(record) {
    try {
        await navigator.clipboard.writeText([
            ['Tarih', dateLabel(record.waste_date)], ['Atık kodu', record.waste_code],
            ['UN', record.un_number], ['ADR adı', record.adr_name], ['Sınıf', record.adr_class],
            ['Üretici tesis unvanı', displayRecordValue(record, 'producer_name')],
            ['İşleme tesisi unvanı', displayRecordValue(record, 'processor_name')],
            ['Üretici adresi', record.address], ['İşleme tesisi adresi', record.processor_address],
            ['Üretici VKN', record.producer_vkn], ['Alıcı VKN', record.processor_vkn],
        ].map(([label, value]) => label + '\t' + (value ?? '')).join('\n'));
        copied.value = 'Seçilen ADR satırı kopyalandı.';
    } catch {
        copied.value = 'Satır kopyalanamadı.';
    }
}
const fields = {
    waste_date: 'Taşıma tarihi', waste_code: 'Atık kodu', waste_name: 'Atık açıklaması',
    tkn: 'TKN', tdn: 'TDN', producer_name: 'Üretici tesis', processor_name: 'İşleme tesisi',
    producer_vkn: 'Üretici VKN', processor_vkn: 'İşleme tesisi VKN', district: 'İlçe',
    address: 'Üretici adresi', processor_address: 'İşleme tesisi adresi',
    observed_amount: 'Gözlenen miktar', period: 'Periyot',
    ...(props.admin ? { plate: 'Plaka' } : {}),
};
const edition = ref(null);
const confirmingMissing = ref(false);
const requiredFields = ['waste_date', 'waste_code', 'producer_name', 'processor_name'];
const fieldGroups = [
    { title: 'Taşıma ve tesis bilgileri', keys: ['waste_date', 'waste_code', 'producer_name', 'processor_name', 'waste_name'] },
    { title: 'MOTAT numaraları', keys: ['tkn', 'tdn'] },
    { title: 'Ek bilgiler', keys: Object.keys(fields).filter(key => ![...requiredFields, 'waste_name', 'tkn', 'tdn'].includes(key)) },
];
const detail = ref(null);
const tab = ref('motat');
const params = new URLSearchParams(window.location.search);
const filterDate = key => /^\d{4}-\d{2}-\d{2}$/.test(params.get(key) || '') ? params.get(key) : '';
const filters = ref({ search: '', from: filterDate('from'), to: filterDate('to'), producer: '', processor: '', order: 'desc',
    cancelled: false });
const invalidExportDates = computed(() => filters.value.from && filters.value.to && filters.value.to < filters.value.from);
const importFile = ref(null);
const importPreview = ref(null);
const importBusy = ref(false);
const importMessage = ref('');
async function runImport(confirm = false) {
    importBusy.value = true;
    importMessage.value = '';
    const data = new FormData();
    if (confirm) data.append('token', importPreview.value.token);
    else {
        importPreview.value = null;
        data.append('excel', importFile.value);
    }
    try {
        const response = await fetch('/atik-takip/ice-aktar/' + (confirm ? 'onay' : 'onizleme'), {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: data,
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'Aktarım yapılamadı.');
        if (confirm) {
            importPreview.value = null;
            importMessage.value = result.inserted + ' yeni taşıma kaydı eklendi.';
            router.reload({ only: ['records'] });
        } else importPreview.value = result;
    } catch (error) {
        importMessage.value = error.message;
    } finally {
        importBusy.value = false;
    }
}
const excelUrl = computed(() => '/atik-takip/kayitlar/excel?' + new URLSearchParams({
    from: filters.value.from, to: filters.value.to,
}));
const dateKey = value => String(value ?? '').slice(0, 10);
const dateLabel = value => dateKey(value).split('-').reverse().join('.');
const form = useForm(Object.fromEntries(Object.keys(fields).map(key => [key, ''])));
const facilityRecord = ref({});
const facilityMissing = computed(() => Object.fromEntries(['producer', 'processor'].map(type => [
    type, facilityRecord.value[type] ? [
        ...(!/^\d{10}$/.test(String(form[type + '_vkn'] ?? '').trim()) ? ['VKN'] : []),
        ...(!String(form[addressField(type)] ?? '').trim() ? ['açık adres'] : []),
    ] : [],
])));
const missingNumbers = computed(() => ['tkn', 'tdn'].filter(key => !String(form[key] ?? '').trim()).map(key => fields[key]));
function open(record = {}) {
    newFacility.value = {};
    facilityRecord.value = record.id ? { producer: record.id, processor: record.id } : {};
    chosenFacility.value = { producer: record.producer_name, processor: record.processor_name };
    form.clearErrors();
    confirmingMissing.value = false;
    Object.keys(fields).forEach(key => { form[key] = record[key] ?? ''; });
    changeWasteCode(form.waste_code);
    form.waste_date = dateKey(record.waste_date) || props.today;
    edition.value = record.id ?? 0;
    formFacilityFocus.value = '';
}
function save(confirmed = false) {
    const pending = Object.keys(facilityTypes).filter(type =>
        !newFacility.value[type] && chosenFacility.value[type] !== form[type + '_name'],
    );
    if (pending.length) {
        for (const type of pending) {
            form.setError(type + '_name', 'Önerilerden tesis seçin veya “Yeni tesis olarak gir” seçeneğini kullanın.');
        }
        confirmingMissing.value = false;
        return;
    }
    const missing = Object.keys(facilityTypes).flatMap(type => facilityMissing.value[type].map(field => (
        field === 'VKN'
            ? [type + '_vkn', 'Tesisin 10 haneli VKN’sini girin.']
            : [addressField(type), 'Tesisin açık adresini girin.']
    )));
    if (missing.length) {
        form.setError(Object.fromEntries(missing));
        confirmingMissing.value = false;
        document.getElementById('transport-' + missing[0][0])?.focus();
        return;
    }
    if (missingNumbers.value.length && !confirmed) {
        confirmingMissing.value = true;
        return;
    }
    const options = {
        preserveScroll: true,
        onSuccess: () => { edition.value = null; detail.value = null; },
        onError: () => { confirmingMissing.value = false; },
    };
    form.transform(data => ({ ...data,
        new_facility: Object.keys(newFacility.value).length ? newFacility.value : undefined,
        facility_record: Object.keys(facilityRecord.value).length ? facilityRecord.value : undefined,
        confirm_missing_motat: confirmed && missingNumbers.value.length > 0,
    }));
    if (edition.value) form.put('/atik-takip/kayitlar/' + edition.value, options);
    else form.post('/atik-takip/kayitlar', options);
}
const editable = record => props.canWrite && record.transport_status !== 'cancelled'
    && (props.admin || dateKey(record.waste_date) >= props.today);
function markNotOccurred(record) {
    if (!window.confirm('Taşıma gerçekleşmedi olarak işaretlenip aktif listeden çıkarılsın mı?')) return;
    router.patch('/atik-takip/kayitlar/' + record.id + '/gerceklesmedi', {}, {
        preserveScroll: true,
        onSuccess: () => { if (detail.value?.id === record.id) detail.value = null; },
    });
}
const facilityTypes = { producer: 'Üretici tesis', processor: 'İşleme tesisi' };
const cleanName = value => String(value ?? '').trim().replace(/\s+/gu, ' ');
const displayFacilityName = value => cleanName(value)
    .replace(/^\d+\s*-\s*/u, '')
    .replace(/\s*\(\s*[İIÇC]KN\s*:\s*\d+\s*\)\s*$/iu, '')
    .trim().toLocaleUpperCase('tr-TR');
const displayRecordValue = (record, key) => ['producer_name', 'processor_name'].includes(key)
    ? displayFacilityName(record[key.replace('_name', '_confirmed_facility')]?.name ?? record[key]) : record[key];
const nameKey = value => cleanName(value).normalize('NFC').toLocaleLowerCase('tr');
const searchKey = value => nameKey(value).normalize('NFD').replace(/\p{M}/gu, '').replace(/ı/g, 'i');
const locationKey = value => searchKey(value).replace(/[^a-z0-9]+/g, ' ').trim();
function facilityIdentity(name) {
    const identifiers = [...searchKey(name).matchAll(/\b([ic]kn)\s*:\s*(\d{6,})\b/g)];
    return {
        base: locationKey(searchKey(name).replace(/^\d+\s*-\s*/, '').replace(/\(?\b[ic]kn\s*:\s*\d{6,}\s*\)?/g, '')),
        prefix: name.match(/^(\d+)\s*-/)?.[1],
        identifiers,
    };
}
const facilityGroups = computed(() => Object.fromEntries(Object.keys(facilityTypes).map(type => {
    const candidates = new Map();
    for (const record of props.records) {
        const name = cleanName(record[type + '_name']);
        if (!name) continue;
        const confirmed = record[type + '_confirmed_facility'];
        const { base, prefix, identifiers } = facilityIdentity(name);
        const evidence = {
            prefix,
            ...Object.fromEntries(identifiers.map(([, kind, id]) => [kind, id])),
            vkn: String(record[type + '_vkn'] ?? '').trim(),
            address: locationKey(type === 'producer' ? record.address : record.processor_address),
            district: type === 'processor' ? locationKey(record.district) : '',
        };
        const key = confirmed ? 'confirmed:' + confirmed.id
            : prefix ? 'facility:' + prefix : base || nameKey(name);
        if (!candidates.has(key)) candidates.set(key, []);
        candidates.get(key).push({ record, name, evidence, confirmed });
    }
    const groups = new Map();
    for (const [base, entries] of candidates) {
        const conflicts = entries[0].confirmed ? [] : Object.keys(entries[0].evidence).filter(field => {
            if (base.startsWith('facility:') && ['vkn', 'district'].includes(field)) return false;
            return new Set(entries.map(entry => entry.evidence[field]).filter(Boolean)).size > 1;
        });
        for (const { record, name, evidence, confirmed } of entries) {
            const key = JSON.stringify([base, ...conflicts.map(field => evidence[field] || '')]);
            if (!groups.has(key)) groups.set(key, { key, name, confirmed, aliases: new Set(), ids: new Set() });
            const group = groups.get(key);
            if (name.length < group.name.length) group.name = name;
            if (confirmed) group.name = confirmed.name;
            group.aliases.add(name);
            group.ids.add(record.id);
        }
    }
    return [type, [...groups.values()].map(group => {
        const labels = [group.name, ...group.aliases].map(displayFacilityName).filter(Boolean);
        return { ...group, label: labels.sort((a, b) => b.length - a.length)[0] || group.name };
    }).sort((a, b) => a.label.localeCompare(b.label, 'tr'))];
})));
function matchingFacilities(type, query) {
    const terms = searchKey(query).split(' ').filter(Boolean);
    return facilityGroups.value[type].filter(group =>
        [...group.aliases].some(alias => terms.every(term => searchKey(alias).includes(term))),
    );
}
const formFacilityFocus = ref('');
const facilityRows = computed(() => new Map(props.records.map(record => [record.id, record])));
const addressField = type => type === 'producer' ? 'address' : 'processor_address';
function facilityAddresses(type, group, records) {
    if (group.confirmed?.card_id && group.confirmed.address) return [group.confirmed.address];
    const addressRows = type === 'producer' && group.confirmed?.id === '91908'
        ? records.filter(record => searchKey(record.address).includes('gayrettepe'))
        : records;
    return uniqueValues(addressRows, addressField(type), locationKey);
}
function uniqueValues(records, field, normalize = cleanName) {
    return [...new Map(records.flatMap(record => {
        const value = cleanName(record[field]);
        return value ? [[normalize(value), value]] : [];
    })).values()];
}
function chooseFormFacility(type, group) {
    delete newFacility.value[type];
    form.clearErrors(type + '_name');
    chosenFacility.value[type] = group.name;
    const records = [...group.ids].map(id => facilityRows.value.get(id));
    facilityRecord.value[type] = (records.find(record => cleanName(record[type + '_name']) === group.name) ?? records[0]).id;
    form[type + '_name'] = group.name;
    const numbers = uniqueValues(records.filter(record => !record[type + '_vkn_suspect']), type + '_vkn');
    const addresses = facilityAddresses(type, group, records);
    const needsReview = records.some(record => record[type + '_vkn_suspect']);
    form[type + '_vkn'] = group.confirmed?.vkn || (numbers.length === 1 && !needsReview ? numbers[0] : '');
    form[addressField(type)] = addresses.length === 1 ? addresses[0] : '';
    if (type === 'processor') {
        const districts = uniqueValues(records, 'district', locationKey);
        form.district = group.confirmed?.district || (districts.length === 1 ? districts[0] : '');
    }
    formFacilityFocus.value = '';
}
function changeFacilityName(type) {
    delete newFacility.value[type];
    delete facilityRecord.value[type];
    delete chosenFacility.value[type];
    form[type + '_vkn'] = '';
    form[addressField(type)] = '';
    if (type === 'processor') form.district = '';
    formFacilityFocus.value = type;
}
function declareNewFacility(type) {
    newFacility.value[type] = true;
    delete facilityRecord.value[type];
    form.clearErrors(type + '_name');
    formFacilityFocus.value = '';
}
const directory = computed(() => {
    const type = props.facilityView;
    if (!type) return [];
    return matchingFacilities(type, facilitySearch.value).map(group => {
        const records = [...group.ids].map(id => facilityRows.value.get(id));
        return {
            ...group,
            vkn: group.confirmed?.vkn || uniqueValues(records.filter(record => !record[type + '_vkn_suspect']), type + '_vkn').join(' / '),
            suspectVkn: group.confirmed?.vkn ? '' : uniqueValues(records, type + '_vkn_original').join(' / '),
            address: facilityAddresses(type, group, records).join(' / '),
            district: group.confirmed?.district && type === 'processor' ? group.confirmed.district
                : type === 'processor' ? uniqueValues(records, 'district', locationKey).join(' / ') : '',
        };
    });
});
function companyKey(value) {
    return facilityIdentity(cleanName(value)).base
        .replace(/\s+\b(?:subesi|sube|tesisi|fabrikasi|deposu)\b.*$/u, '').trim();
}
function suggestedNumbers(type) {
    const name = companyKey(form[type + '_name']);
    if (name.length < 6 || name.split(' ').length < 2) return [];
    const numbers = new Set();
    for (const record of props.records) {
        for (const role of [type, type === 'producer' ? 'processor' : 'producer']) {
            if (record[role + '_vkn_suspect']) continue;
            const candidate = companyKey(record[role + '_name']);
            const number = cleanName(record[role + '_vkn']);
            if (candidate.length >= 6 && candidate.split(' ').length >= 2 && /^\d{10}$/.test(number)
                && (candidate === name || candidate.startsWith(name + ' ') || name.startsWith(candidate + ' '))) {
                numbers.add(number);
            }
        }
    }
    return [...numbers].slice(0, 5);
}
const wasteEntry = computed(() => props.wasteCatalog.codes[form.waste_code.replace(/\s+/g, '').replace(/\*+$/, '')]);
function changeWasteCode(value) {
    form.waste_code = value;
    form.waste_name = wasteEntry.value?.description ?? '';
}
const selectedFacilities = ref({});
const focusedFacility = ref('');
function selectFacility(type, group) {
    filters.value[type] = group.label;
    selectedFacilities.value[type] = group;
    focusedFacility.value = '';
}
function matchingIds(type, query) {
    if (!query.trim()) return null;
    const selection = selectedFacilities.value[type];
    const groups = selection?.label === query
        ? facilityGroups.value[type].filter(group => group.key === selection.key)
        : matchingFacilities(type, query);
    return new Set(groups.flatMap(group => [...group.ids]));
}
const filtered = computed(() => {
    const { search, from, to, producer, processor, order, cancelled } = filters.value;
    const producerIds = matchingIds('producer', producer);
    const processorIds = matchingIds('processor', processor);
    const result = props.records.filter(record => {
        const day = dateKey(record.waste_date);
        return (!from || day >= from) && (!to || day <= to)
            && (cancelled === (record.transport_status === 'cancelled'))
            && (!producerIds || producerIds.has(record.id))
            && (!processorIds || processorIds.has(record.id))
            && Object.values(record).some(value => String(value ?? '').toLocaleLowerCase('tr').includes(search.trim().toLocaleLowerCase('tr')));
    });
    return order === 'asc' ? result.reverse() : result;
});
const inputStyle = 'w-full rounded-lg border border-slate-300 bg-white p-2 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:scheme-dark';
const pageSize = 25;
const page = ref(1);
const pageCount = computed(() => Math.max(1, Math.ceil(filtered.value.length / pageSize)));
const pageStart = computed(() => (page.value - 1) * pageSize);
const visibleRecords = computed(() => filtered.value.slice(pageStart.value, pageStart.value + pageSize));
watch(filters, () => { page.value = 1; }, { deep: true });
watch(pageCount, count => { page.value = Math.min(page.value, count); });
const columns = computed(() => tab.value === 'motat'
    ? { waste_date: 'Tarih', waste_code: 'Atık kodu', waste_name: 'Açıklama', tkn: 'TKN', tdn: 'TDN', producer_name: 'Üretici', processor_name: 'İşleme tesisi' }
    : { waste_date: 'Tarih', waste_code: 'Atık kodu', un_number: 'UN', adr_name: 'ADR adı', adr_class: 'Sınıf', producer_name: 'Üretici tesis unvanı', address: 'Üretici adresi', processor_name: 'İşleme tesisi unvanı', processor_address: 'İşleme tesisi adresi', producer_vkn: 'Üretici VKN', processor_vkn: 'Alıcı VKN' });
if (props.canWrite && new URLSearchParams(window.location.search).has('yeni')) open();

const vehiclePlates = ['34PFH301', '34PTR373'];
const monthlyVehicleTargets = [
    { label: 'Çalışma günü hedefi', field: 'workingDays', target: 26 },
    { label: 'Üretici tesis ziyareti hedefi', field: 'producerDayCount', target: 52 },
];
const annualVehicleTargets = monthlyVehicleTargets.map(goal => ({ ...goal, target: goal.target * 12 }));
const targetPercentage = (actual, target) => new Intl.NumberFormat('tr-TR', {
    style: 'percent', maximumFractionDigits: 1,
}).format(actual / target);
const normalizePlate = value => String(value ?? '').toUpperCase().replace(/[^A-Z0-9]/g, '');
const latestVehicleRecord = props.records.find(record => vehiclePlates.includes(normalizePlate(record.plate)));
const statisticsViewMode = ref('month');
const statisticsMonth = ref(dateKey(latestVehicleRecord?.waste_date || props.today).slice(0, 7));
const statisticsYear = ref(dateKey(latestVehicleRecord?.waste_date || props.today).slice(0, 4));
const statisticsYears = computed(() => [...new Set([
    ...props.records.filter(record => record.transport_status !== 'cancelled'
        && vehiclePlates.includes(normalizePlate(record.plate)))
        .map(record => dateKey(record.waste_date).slice(0, 4)).filter(year => /^\d{4}$/.test(year)),
    statisticsYear.value,
])].sort((a, b) => b.localeCompare(a)));
const statisticsGroupKeys = computed(() => Object.fromEntries(Object.entries(facilityGroups.value).map(([type, groups]) => [
        type, new Map(groups.flatMap(group => [...group.ids].map(id => [id, group.key]))),
    ])));
function summarizeVehicle(plate, records) {
    const days = new Map();
    const producers = new Set();
    const processors = new Set();
    for (const record of records) {
        if (normalizePlate(record.plate) !== plate) continue;
        const date = dateKey(record.waste_date);
        if (!days.has(date)) days.set(date, { date, producers: new Set(), processors: new Set(), rows: 0 });
        const day = days.get(date);
        day.rows++;
        const producer = statisticsGroupKeys.value.producer.get(record.id);
        const processor = statisticsGroupKeys.value.processor.get(record.id);
        if (producer) { producers.add(producer); day.producers.add(producer); }
        if (processor) { processors.add(processor); day.processors.add(processor); }
    }
    const daily = [...days.values()].sort((a, b) => b.date.localeCompare(a.date));
    return {
        plate, daily, workingDays: daily.length, producerCount: producers.size, processorCount: processors.size,
        producerDayCount: daily.reduce((sum, day) => sum + day.producers.size, 0),
        rows: daily.reduce((sum, day) => sum + day.rows, 0),
    };
}
const selectedMonthRecords = computed(() => props.records.filter(record => record.transport_status !== 'cancelled'
    && dateKey(record.waste_date).slice(0, 7) === statisticsMonth.value));
const vehicleStats = computed(() => ({
    vehicles: vehiclePlates.map(plate => summarizeVehicle(plate, selectedMonthRecords.value)),
    missingPlates: selectedMonthRecords.value.filter(record => !normalizePlate(record.plate)).length,
}));
const selectedYearRecords = computed(() => props.records.filter(record => record.transport_status !== 'cancelled'
    && dateKey(record.waste_date).slice(0, 4) === statisticsYear.value));
const annualVehicleStats = computed(() => ({
    vehicles: vehiclePlates.map(plate => {
        const yearlyRecords = selectedYearRecords.value;
        const monthly = Array.from({ length: 12 }, (_, index) => {
            const month = `${statisticsYear.value}-${String(index + 1).padStart(2, '0')}`;
            return {
                month,
                label: new Intl.DateTimeFormat('tr-TR', { month: 'long' }).format(new Date(`${month}-01T12:00:00`)),
                ...summarizeVehicle(plate, yearlyRecords.filter(record => dateKey(record.waste_date).slice(0, 7) === month)),
            };
        });
        return { ...summarizeVehicle(plate, yearlyRecords), monthly };
    }),
    missingPlates: selectedYearRecords.value.filter(record => !normalizePlate(record.plate)).length,
}));
</script>

<template>
    <Head :title="statisticsView ? 'Araç İstatistikleri' : facilityView ? facilityTitle : 'Taşıma Kayıtları'" />
    <section v-if="statisticsView" class="space-y-5">
        <h1 class="text-2xl font-bold">Araç İstatistikleri</h1>
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex gap-2" role="group" aria-label="İstatistik dönemi">
                <button type="button" :aria-pressed="statisticsViewMode === 'month'"
                    class="rounded-lg border px-3 py-2 text-sm font-semibold"
                    :class="statisticsViewMode === 'month' ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-slate-400'"
                    @click="statisticsViewMode = 'month'">Aylık</button>
                <button type="button" :aria-pressed="statisticsViewMode === 'year'"
                    class="rounded-lg border px-3 py-2 text-sm font-semibold"
                    :class="statisticsViewMode === 'year' ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-slate-400'"
                    @click="statisticsViewMode = 'year'">Yıllık</button>
            </div>
            <label v-if="statisticsViewMode === 'month'" class="block max-w-xs">Ay<input v-model="statisticsMonth" type="month" :class="inputStyle" /></label>
            <label v-else class="block max-w-xs">Yıl<select v-model="statisticsYear" :class="inputStyle">
                <option v-for="year in statisticsYears" :key="year" :value="year">{{ year }}</option>
            </select></label>
        </div>
        <p v-if="statisticsViewMode === 'month'">Çalışma günü, seçili ayda araç için en az bir taşıma satırı bulunan tarihtir. Aynı gün aynı üreticideki atık kalemleri tek tesis günü olarak sayılır.</p>
        <p v-else>Yıllık toplamlar seçilen takvim yılındaki kayıtlardan hesaplanır. Hedefler aylık hedeflerin 12 katıdır; yıl tamamlanmadıysa gerçekleşen toplam yıl sonu tahmini içermez.</p>
        <p v-if="statisticsViewMode === 'month' && vehicleStats.missingPlates" class="text-amber-700 dark:text-amber-300">{{ vehicleStats.missingPlates }} satırda plaka eksik.</p>
        <p v-if="statisticsViewMode === 'year' && annualVehicleStats.missingPlates" class="text-amber-700 dark:text-amber-300">{{ annualVehicleStats.missingPlates }} satırda plaka eksik.</p>
        <article v-for="vehicle in (statisticsViewMode === 'month' ? vehicleStats.vehicles : annualVehicleStats.vehicles)" :key="vehicle.plate" class="space-y-3 rounded-lg border border-slate-500/40 p-4">
            <h2 class="text-lg font-semibold">{{ vehicle.plate }}</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <div v-for="goal in (statisticsViewMode === 'month' ? monthlyVehicleTargets : annualVehicleTargets)" :key="goal.field" class="rounded-lg bg-slate-100 p-3 dark:bg-slate-800">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <span>{{ goal.label }}</span>
                        <span class="font-semibold">{{ vehicle[goal.field] }} / {{ goal.target }} · {{ targetPercentage(vehicle[goal.field], goal.target) }}</span>
                    </div>
                    <progress class="mt-2 h-2 w-full accent-emerald-600" :aria-label="goal.label"
                        :value="Math.min(vehicle[goal.field], goal.target)" :max="goal.target"></progress>
                </div>
            </div>
            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                <div v-for="[label, value] in [
                    ['Kayıtlı çalışma günü', vehicle.workingDays], ['Farklı üretici tesisi', vehicle.producerCount],
                    ['Farklı işleme tesisi', vehicle.processorCount], ['Üretici tesis günü', vehicle.producerDayCount],
                    ['Taşıma satırı', vehicle.rows], ['Kilometre', 'Arvento verisi yok'],
                ]" :key="label"><dt class="text-xs">{{ label }}</dt><dd class="font-semibold">{{ value }}</dd></div>
            </dl>
            <details v-if="statisticsViewMode === 'month' && vehicle.rows">
                <summary class="cursor-pointer underline">Günlük tesis sayılarını göster</summary>
                <div class="overflow-x-auto">
                    <table class="mt-2 w-full text-left text-sm">
                        <thead><tr><th class="p-2">Tarih</th><th class="p-2">Üretici tesisi</th><th class="p-2">İşleme tesisi</th><th class="p-2">Taşıma satırı</th></tr></thead>
                        <tbody><tr v-for="day in vehicle.daily" :key="day.date" class="border-t border-slate-500/30">
                            <td class="p-2">{{ dateLabel(day.date) }}</td><td class="p-2">{{ day.producers.size }}</td>
                            <td class="p-2">{{ day.processors.size }}</td><td class="p-2">{{ day.rows }}</td>
                        </tr></tbody>
                    </table>
                </div>
            </details>
            <div v-else-if="statisticsViewMode === 'year' && vehicle.rows" class="overflow-x-auto">
                <h3 class="mb-2 font-semibold">{{ statisticsYear }} aylık dökümü</h3>
                <table class="w-full min-w-[680px] text-left text-sm">
                    <thead class="border-b border-slate-500/40">
                        <tr><th class="p-2">Ay</th><th class="p-2">Çalışma günü</th><th class="p-2">Üretici tesisi</th><th class="p-2">İşleme tesisi</th><th class="p-2">Üretici tesis günü</th><th class="p-2">Taşıma satırı</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="month in vehicle.monthly" :key="month.month" class="border-b border-slate-500/20">
                            <td class="p-2 capitalize">{{ month.label }}</td><td class="p-2">{{ month.workingDays }}</td>
                            <td class="p-2">{{ month.producerCount }}</td><td class="p-2">{{ month.processorCount }}</td>
                            <td class="p-2">{{ month.producerDayCount }}</td><td class="p-2">{{ month.rows }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else-if="statisticsViewMode === 'month'">Seçili ayda bu plakaya ait taşıma kaydı yok.</p>
            <p v-else>Seçili yılda bu plakaya ait taşıma kaydı yok.</p>
        </article>
    </section>
    <section v-else-if="facilityView" class="space-y-4">
        <h1 class="text-2xl font-bold">{{ facilityTitle }}</h1>
        <p>Yeni tesisler taşıma kaydından eklenir. Yönetici, mevcut tesisi firma ve şube kartına bağlayabilir.</p>
        <form v-if="admin && cardEditing" class="grid gap-3 rounded border p-4 sm:grid-cols-2"
            @submit.prevent="facilityCard.put('/atik-takip/tesis-karti', { preserveScroll: true, onSuccess: () => cardEditing = false })">
            <label v-for="[key, label] in [['company_name','Firma unvanı'],['vkn','Firma VKN'],['name','Tesis / şube adı'],['ckn','ÇKN'],['address','Tesis adresi'],['district','İlçe']]" :key="key">
                {{ label }}<input v-model="facilityCard[key]" :class="inputStyle" :required="['company_name','vkn','name'].includes(key)" />
                <span v-if="facilityCard.errors[key]" class="text-red-600">{{ facilityCard.errors[key] }}</span>
            </label>
            <div class="flex gap-3">
                <button :disabled="facilityCard.processing" class="rounded bg-emerald-700 px-3 py-2 text-white">Kartı kaydet</button>
                <button type="button" @click="cardEditing = false">Vazgeç</button>
            </div>
        </form>
        <label class="block max-w-md">Tesis adıyla ara<input v-model="facilitySearch" :class="inputStyle" /></label>
        <p>{{ directory.length }} tesis</p>
        <div class="overflow-x-auto rounded border border-slate-500/40">
            <table class="w-full text-left text-sm max-md:block">
                <thead class="bg-slate-100 dark:bg-slate-800 max-md:sr-only"><tr>
                    <th class="p-3">Tesis</th><th class="p-3">VKN</th><th class="p-3">Adres</th>
                    <th v-if="facilityView === 'processor'" class="p-3">İlçe</th><th class="p-3">Taşıma kaydı</th>
                </tr></thead>
                <tbody class="max-md:block max-md:divide-y max-md:divide-slate-500/30">
                    <tr v-for="facility in directory" :key="facility.key" class="border-t border-slate-500/30 max-md:block max-md:p-3">
                        <td class="p-3 max-md:block max-md:p-0 max-md:font-semibold">{{ facility.label }}</td><td class="p-3 max-md:block max-md:px-0 max-md:py-1">
                            <span class="mr-1 font-semibold md:hidden">VKN</span>
                            {{ facility.vkn || '—' }}
                            <p v-if="facility.suspectVkn" class="text-xs text-amber-700 dark:text-amber-300">Kontrol edilecek eski VKN: {{ facility.suspectVkn }}</p>
                        </td>
                        <td class="p-3 max-md:block max-md:px-0 max-md:py-1"><span class="mr-1 font-semibold md:hidden">Adres</span>{{ facility.address || '—' }}</td>
                        <td v-if="facilityView === 'processor'" class="p-3 max-md:block max-md:px-0 max-md:py-1"><span class="mr-1 font-semibold md:hidden">İlçe</span>{{ facility.district || '—' }}</td>
                        <td class="p-3 max-md:block max-md:px-0 max-md:py-1">
                            <span class="mr-1 font-semibold md:hidden">Taşıma kaydı</span>{{ facility.ids.size }}
                            <button v-if="admin" type="button" class="ml-2 underline" @click="editFacilityCard(facility)">{{ facility.confirmed?.card_id ? 'Kartı düzenle' : 'Kart oluştur' }}</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!directory.length" class="p-3">Eşleşen tesis bulunamadı.</p>
        </div>
    </section>
    <div v-else class="space-y-4">
        <details v-if="admin" class="rounded-lg border border-slate-500/40 p-4">
            <summary class="cursor-pointer font-semibold">Excel içe aktar</summary>
            <div class="mt-3 space-y-3">
                <p>Taşıma şablonunu veya aylık çıktıyı seçin. Onayla yalnızca yeni satırlar eklenir.</p>
                <input type="file" accept=".xlsx" aria-label="Taşıma Excel dosyası"
                    @change="event => { importFile = event.target.files[0]; importPreview = null; }" />
                <button type="button" class="rounded border px-3 py-2" :disabled="!importFile || importBusy" @click="runImport()">Önizle</button>
                <p v-if="importMessage" role="status">{{ importMessage }}</p>
                <template v-if="importPreview">
                    <p>{{ importPreview.new }} yeni · {{ importPreview.report.filter(row => row.status === 'Eşleşen').length }} eşleşen · {{ importPreview.report.filter(row => row.status === 'Çelişkili').length }} çelişkili · {{ importPreview.report.filter(row => row.status === 'Eksik veya geçersiz alan').length }} geçersiz</p>
                    <ul class="max-h-64 space-y-1 overflow-auto rounded border p-2 text-sm">
                        <li v-for="entry in importPreview.report" :key="entry.source">{{ entry.source }} · {{ entry.status }} · {{ entry.code }} {{ entry.producer }}</li>
                    </ul>
                    <button type="button" class="rounded bg-emerald-700 px-3 py-2 text-white" :disabled="!importPreview.new || importBusy" @click="runImport(true)">Yeni satırları ekle</button>
                </template>
            </div>
        </details>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Tüm Taşıma Kayıtları</h1>
            <button v-if="canWrite" class="rounded-lg bg-emerald-700 px-4 py-2 text-white" @click="open()">Yeni taşıma kaydı</button>
        </div>
        <form v-if="edition !== null" class="space-y-4 rounded-xl border border-slate-500/40 p-4"
            @submit.prevent="save($event.submitter?.value === 'confirm-missing')" @input="confirmingMissing = false">
            <h2 class="font-bold">{{ edition ? 'MOTAT kaydını düzenle' : 'Yeni MOTAT kaydı' }}</h2>
            <fieldset v-for="group in fieldGroups" :key="group.title" class="rounded-lg border border-slate-500/30 p-3">
                <legend class="px-2 font-semibold">{{ group.title }}</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                <div v-for="key in group.keys" :key="key" class="relative" :class="{ 'sm:col-span-2': key === 'waste_name' }"
                    @focusout="event => { if (!event.currentTarget.contains(event.relatedTarget)) formFacilityFocus = ''; }"
                    @keydown.esc="formFacilityFocus = ''">
                    <label :for="'transport-' + key">{{ fields[key] }}<span v-if="requiredFields.includes(key)" class="text-red-600 dark:text-red-300"> *</span></label>
                    <p v-if="key === 'waste_name'" id="transport-waste_name" class="mt-1 rounded-lg bg-slate-100 p-3 dark:bg-slate-800">{{ form.waste_name || 'Atık kodu girildiğinde açıklama burada görünür.' }}</p>
                    <input v-else v-model="form[key]" :class="inputStyle" :type="key === 'waste_date' ? 'date' : 'text'"
                        :id="'transport-' + key" autocomplete="off"
                        :readonly="key === 'waste_name'"
                        @focus="['producer_name', 'processor_name'].includes(key) && (formFacilityFocus = key.split('_')[0])"
                        @input="key === 'waste_code' ? changeWasteCode($event.target.value) : ['producer_name', 'processor_name'].includes(key) && changeFacilityName(key.split('_')[0])"
                        :required="requiredFields.includes(key)"
                        :min="key === 'waste_date' && !admin ? today : undefined" />
                    <template v-if="formFacilityFocus && key === formFacilityFocus + '_name' && form[key].trim().length >= 2">
                        <ul class="absolute z-30 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-slate-400 bg-white shadow-lg dark:bg-slate-800">
                            <li v-for="group in matchingFacilities(formFacilityFocus, form[key]).slice(0, 10)" :key="group.key">
                                <button type="button" class="w-full px-3 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700"
                                    @click="chooseFormFacility(formFacilityFocus, group)">{{ group.label }} · {{ group.ids.size }} kayıt</button>
                            </li>
                            <li><button type="button" class="w-full px-3 py-2 text-left underline" @click="declareNewFacility(formFacilityFocus)">Yeni tesis olarak gir</button></li>
                        </ul>
                    </template>
                    <p v-if="(key === 'producer_name' && newFacility.producer) || (key === 'processor_name' && newFacility.processor)" class="mt-1 text-sm">
                        Yeni tesis için VKN ve açık adres girin. Kaydederken mevcut tesislerle karşılaştırılacak.
                    </p>
                    <p v-if="['producer_name', 'processor_name'].includes(key) && facilityMissing[key.split('_')[0]].length"
                        class="mt-1 text-sm font-medium text-amber-800 dark:text-amber-300">
                        Bu tesis için eksik {{ facilityMissing[key.split('_')[0]].join(' ve ') }} bilgisini Ek bilgiler bölümünde tamamlayın.
                    </p>
                    <div v-if="key === 'waste_name'" class="mt-1">
                        <a :href="wasteCatalog.source" target="_blank" rel="noopener" class="underline">Atık Yönetimi Yönetmeliği Ek-4</a>
                        <span v-if="wasteEntry?.hazardous"> · Tehlikeli (*) {{ wasteEntry.classification }}</span>
                        <span v-if="form.waste_code && !wasteEntry"> · Kod listede bulunamadı.</span>
                    </div>
                    <div v-if="['producer_vkn', 'processor_vkn'].includes(key) && !form[key]" class="mt-1 space-x-2">
                        <button v-for="number in suggestedNumbers(key.split('_')[0])" :key="number" type="button" class="underline"
                            @click="form[key] = number">Firma VKN önerisi {{ number }}</button>
                    </div>
                    <span v-if="form.errors[key]" class="text-red-600 dark:text-red-300">{{ form.errors[key] }}</span>
                </div>
                </div>
            </fieldset>
            <div v-if="confirmingMissing && missingNumbers.length" class="space-y-2 rounded-lg border border-amber-500 p-3">
                <p role="alert">{{ missingNumbers.join(' ve ') }} eksik. Bu numaralar olmadan kaydetmek istiyor musun?</p>
                <button type="submit" value="confirm-missing" :disabled="form.processing" class="mr-3 rounded-lg bg-amber-800 px-4 py-2 text-white">Eksik bilgilerle kaydet</button>
                <button type="button" class="underline" @click="confirmingMissing = false">Forma dön</button>
            </div>
            <p v-if="form.errors.confirm_missing_motat" role="alert">{{ form.errors.confirm_missing_motat }}</p>
            <p v-if="form.hasErrors" role="status">Kayıt yapılamadı. İşaretli alanları kontrol edin.</p>
            <button v-if="!confirmingMissing" :disabled="form.processing" class="mr-3 rounded-lg bg-emerald-700 px-4 py-2 text-white">Kaydet</button>
            <button type="button" @click="edition = null">Vazgeç</button>
        </form>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <label>Genel arama<input v-model="filters.search" :class="inputStyle" /></label>
            <label>Başlangıç tarihi<input v-model="filters.from" type="date" :class="inputStyle" /></label>
            <label>Bitiş tarihi<input v-model="filters.to" type="date" :class="inputStyle" /></label>
            <div v-for="(label, key) in facilityTypes" :key="key" class="relative"
                @focusout="event => { if (!event.currentTarget.contains(event.relatedTarget)) focusedFacility = ''; }"
                @keydown.esc="focusedFacility = ''">
                <label :for="'facility-' + key">{{ label }}</label>
                <input :id="'facility-' + key" v-model="filters[key]" :class="inputStyle"
                    placeholder="Tesis adının bir kısmını yazın" autocomplete="off"
                    @focus="focusedFacility = key" @input="selectedFacilities[key] = ''" />
                <ul v-if="focusedFacility === key && filters[key].trim()"
                    class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-lg border border-slate-300 bg-white shadow-lg dark:border-slate-600 dark:bg-slate-800">
                    <li v-for="group in matchingFacilities(key, filters[key]).slice(0, 12)" :key="group.key">
                        <button type="button" class="w-full px-3 py-2 text-left hover:bg-slate-100 focus:bg-slate-100 dark:hover:bg-slate-700 dark:focus:bg-slate-700"
                            @click="selectFacility(key, group)">
                            {{ group.label }} <span class="text-slate-500 dark:text-slate-300">· {{ group.ids.size }} kayıt</span>
                        </button>
                    </li>
                    <li v-if="!matchingFacilities(key, filters[key]).length" class="p-3">Eşleşen tesis yok.</li>
                </ul>
            </div>
            <label>Sıralama<select v-model="filters.order" :class="inputStyle"><option value="desc">En yeni</option><option value="asc">En eski</option></select></label>
            <label v-if="admin" class="flex items-center gap-2"><input v-model="filters.cancelled" type="checkbox" /> Gerçekleşmeyenleri göster</label>
        </div>
        <p v-if="filters.from && filters.to && filters.from > filters.to" role="alert">Bitiş tarihi başlangıçtan önce olamaz.</p>
        <div class="flex flex-wrap items-center gap-4">
            <button v-for="name in ['motat', 'adr']" :key="name" :aria-pressed="tab === name" class="rounded border px-3 py-1" :class="tab === name ? 'bg-emerald-700 text-white' : ''" @click="tab = name">{{ name.toUpperCase() }}</button>
            <span>{{ filtered.length }} / {{ records.length }} kayıt</span>
            <button class="underline" @click="filters = { search: '', from: '', to: '', producer: '', processor: '', order: 'desc', cancelled: false }; selectedFacilities = {}">Filtreleri temizle</button>
            <a v-if="!invalidExportDates" :href="excelUrl" class="rounded-lg border border-emerald-700 px-3 py-2 font-semibold text-emerald-800 dark:text-emerald-300"
                title="Seçilen tarih aralığındaki tüm kayıtları aylık sayfalara aktar">Aylık Excel indir</a>
        </div>
        <section v-if="tab === 'adr'" class="space-y-2">
            <p>Geçmişte tek ve tutarlı bir UN eşleşmesi varsa sistem ADR alanlarını doldurur. Birden fazla eşleşme veya eksik bilgi varsa seçimi yetkili kullanıcı yapar. Bakanlık girişinden önce sınıflandırmayı doğrulayın.</p>
            <details v-if="admin" class="rounded border border-slate-500/40 p-3">
                <summary class="cursor-pointer font-semibold">ADR düzenleme yetkisi</summary>
                <form class="mt-3 flex flex-wrap items-end gap-3"
                    @submit.prevent="adrAccess.patch('/atik-takip/adr-yetki', { preserveScroll: true })">
                    <label>Kullanıcı<select v-model="adrAccess.user_id" :class="inputStyle" required>
                        <option value="">Kullanıcı seç</option>
                        <option v-for="user in adrUsers" :key="user.id" :value="user.id">{{ user.name }} · {{ user.can_classify_waste_adr ? 'Yetkili' : 'Yetkisiz' }}</option>
                    </select></label>
                    <label>İşlem<select v-model="adrAccess.enabled" :class="inputStyle"><option :value="true">Yetki ver</option><option :value="false">Yetkiyi kaldır</option></select></label>
                    <button :disabled="adrAccess.processing" class="rounded border px-3 py-2">Uygula</button>
                </form>
            </details>
            <p v-if="copied" role="status">{{ copied }}</p>
        </section>
        <section v-if="detail" class="space-y-3 rounded-lg border border-slate-500/40 p-4">
            <div class="flex justify-between"><h2 class="font-bold">Kayıt ayrıntısı · {{ dateLabel(detail.waste_date) }}</h2><button @click="detail = null">Kapat</button></div>
            <dl class="grid gap-3 sm:grid-cols-3">
                <div v-for="(label, key) in { ...fields, plate: 'Plaka', ...columns, un_number: 'UN numarası', adr_name: 'ADR adı', adr_class: 'ADR sınıfı' }" :key="key">
                    <dt class="font-semibold">{{ label }}</dt><dd>{{ (key === 'waste_date' ? dateLabel(detail[key]) : detail[key]) || '—' }}</dd>
                </div>
            </dl>
            <h3 class="font-bold">Değişiklik geçmişi</h3>
            <p v-if="!history[detail.id]?.length">Kaydedilmiş değişiklik yok.</p>
            <details v-for="entry in history[detail.id] || []" :key="entry.id">
                <summary>{{ entry.actor }} · {{ dateLabel(entry.created_at) }} {{ entry.created_at.slice(11, 16) }}</summary>
                <p v-for="(change, key) in JSON.parse(entry.changes)" :key="key">{{ fields[key] || key }} · {{ change.before ?? '—' }} → {{ change.after ?? '—' }}</p>
            </details>
        </section>
        <div class="overflow-x-auto rounded-lg border border-slate-500/40">
            <table class="w-full text-left text-xs max-md:block max-md:text-sm">
                <thead class="bg-slate-100 dark:bg-slate-800 max-md:sr-only"><tr><th v-for="label in columns" :key="label" class="p-3">{{ label }}</th><th class="p-3">İşlemler</th></tr></thead>
                <tbody class="max-md:block">
                    <tr v-for="record in visibleRecords" :key="record.id" class="border-t border-slate-500/30 max-md:block max-md:p-3">
                        <td v-for="(label, key) in columns" :key="key" class="p-3 max-md:block max-md:px-0 max-md:py-1 max-md:break-words">
                            <span class="mr-2 font-semibold md:hidden">{{ label }}</span>
                            <select v-if="tab === 'adr' && adrRow === record.id && key === 'un_number'"
                                :value="defaultUnNumbers.includes(adrForm.un_number) ? adrForm.un_number : ''"
                                @change="adrForm.un_number = $event.target.value; adrForm.manual_entry = false; showOtherUn = false"
                                :class="inputStyle" aria-label="UN numarası">
                                <option value="">UN seçin</option>
                                <option v-for="number in defaultUnNumbers" :key="number" :value="number">{{ number }} · {{ adrCatalog[number].adr_name }}</option>
                            </select>
                            <span v-else>{{ (key === 'waste_date' ? dateLabel(record[key]) : tab === 'adr' && adrRow === record.id && ['adr_name', 'adr_class'].includes(key) ? (adrCatalog[adrForm.un_number]?.[key] || (adrForm.manual_entry ? adrForm['manual_' + key] : '')) : displayRecordValue(record, key)) || '—' }}</span>
                            <span v-if="tab === 'adr' && key === 'un_number' && adrRow === record.id && adrForm.un_number && !defaultUnNumbers.includes(adrForm.un_number)" class="block text-xs">Farklı UN seçildi · {{ adrForm.un_number }}</span>
                            <span v-if="tab === 'adr' && key === 'un_number' && adrRow !== record.id && !record.un_number" class="text-xs text-amber-700 dark:text-amber-300"> Seçim bekliyor</span>
                            <span v-else-if="tab === 'adr' && key === 'un_number' && adrRow !== record.id" class="block text-xs">{{ !adrCatalog[record.un_number] && !record.adr_reference ? 'Katalog dışında · İnceleme gerekli' : record.adr_approved_at ? 'Onaylı' : 'Onay bekliyor' }}</span>
                        </td>
                        <td class="space-x-2 whitespace-nowrap p-3 max-md:flex max-md:flex-wrap max-md:gap-3 max-md:whitespace-normal max-md:px-0 max-md:py-2">
                            <button class="underline" @click="detail = record">Detay</button>
                            <button v-if="tab === 'motat' && editable(record)" class="underline" @click="open(record)">Düzenle</button>
                            <button v-if="admin && tab === 'motat' && editable(record)" type="button" class="underline text-red-700 dark:text-red-300" @click="markNotOccurred(record)">Gerçekleşmedi</button>
                            <span v-if="admin && record.transport_status === 'cancelled'" class="font-semibold">Gerçekleşmedi</span>
                            <template v-if="tab === 'adr' && canClassifyAdr && record.transport_status !== 'cancelled'">
                                <template v-if="adrRow === record.id">
                                    <button :disabled="!adrForm.un_number || manualUnMissingDetails || adrForm.processing" class="underline disabled:opacity-40" @click="saveAdr(record)">Kaydet</button>
                                    <button class="underline" @click="adrRow = null">Vazgeç</button>
                                    <details :open="showOtherUn" class="mt-2 min-w-48 max-w-72 whitespace-normal" @toggle="showOtherUn = $event.target.open">
                                        <summary class="cursor-pointer underline">Farklı UN numarası</summary>
                                        <label class="mt-2 block">Katalogdan seç
                                            <select :class="inputStyle" :value="adrCatalog[adrForm.un_number] && !defaultUnNumbers.includes(adrForm.un_number) ? adrForm.un_number : ''"
                                                @change="adrForm.un_number = $event.target.value; adrForm.manual_entry = false">
                                                <option value="">Seçiniz</option>
                                                <option v-for="entry in otherCatalogEntries" :key="entry.un_number" :value="entry.un_number">{{ entry.un_number }} · {{ entry.adr_name }}</option>
                                            </select>
                                        </label>
                                        <label class="mt-2 flex items-center gap-2"><input type="checkbox" :checked="adrForm.manual_entry"
                                            @change="adrForm.manual_entry = $event.target.checked; adrForm.un_number = ''" /> Katalog dışı UN gir</label>
                                        <div v-if="adrForm.manual_entry" class="mt-2 space-y-2">
                                            <label class="block">UN numarası<input v-model.trim="adrForm.un_number" inputmode="numeric" maxlength="4" :class="inputStyle" /></label>
                                            <label class="block">Uygun sevkiyat adı<input v-model.trim="adrForm.manual_adr_name" maxlength="500" :class="inputStyle" /></label>
                                            <label class="block">ADR sınıfı<input v-model.trim="adrForm.manual_adr_class" :class="inputStyle" placeholder="Örn. 6.1" /></label>
                                            <label class="block">Doğrulama kaynağı<input v-model.trim="adrForm.adr_reference" maxlength="500" :class="inputStyle" placeholder="ADR tablosu veya belge referansı" /></label>
                                        </div>
                                    </details>
                                    <p v-if="adrForm.errors.un_number" role="alert" class="whitespace-normal">{{ adrForm.errors.un_number }}</p>
                                    <p v-if="adrForm.errors.manual_adr_class" role="alert" class="whitespace-normal">Geçerli bir ADR sınıfı girin.</p>
                                </template>
                                <template v-else>
                                    <button class="underline" @click="editAdr(record)">UN seç</button>
                                    <button v-if="!record.adr_approved_at && adrCatalog[record.un_number] && record.adr_name && record.adr_class"
                                        type="button" class="underline" @click="approveAdr(record)">Mevcut ADR’yi onayla</button>
                                </template>
                            </template>
                            <button v-if="tab === 'adr' && record.transport_status !== 'cancelled' && record.adr_approved_at && (adrCatalog[record.un_number] || record.adr_reference) && record.adr_name && record.adr_class" class="underline" @click="copyAdr(record)">Kopyala</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!filtered.length" class="p-4">Bu filtrelerle eşleşen kayıt yok.</p>
        </div>
        <nav v-if="filtered.length" aria-label="Taşıma listesi sayfaları" class="flex flex-wrap items-center justify-between gap-3">
            <span aria-live="polite">{{ filtered.length }} kayıttan {{ pageStart + 1 }}–{{ pageStart + visibleRecords.length }} gösteriliyor</span>
            <div class="flex items-center gap-3">
                <button type="button" class="rounded border px-3 py-1 disabled:opacity-40" :disabled="page === 1" @click="page--">Önceki</button>
                <span>Sayfa {{ page }} / {{ pageCount }}</span>
                <button type="button" class="rounded border px-3 py-1 disabled:opacity-40" :disabled="page === pageCount" @click="page++">Sonraki</button>
            </div>
        </nav>
    </div>
</template>
