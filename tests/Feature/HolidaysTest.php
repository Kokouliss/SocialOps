<?php

use App\Models\Client;
use App\Models\User;
use App\Planning\Holidays;
use App\Planning\PlanRequest;
use Carbon\CarbonImmutable;

test('Orthodox and Western Easter and the days around them are right for the years that matter', function () {
    expect(array_map(fn ($y) => Holidays::orthodoxEaster($y)->toDateString(), [2024, 2025, 2026, 2027, 2028]))
        ->toBe(['2024-05-05', '2025-04-20', '2026-04-12', '2027-05-02', '2028-04-16'])
        ->and(array_map(fn ($y) => Holidays::westernEaster($y)->toDateString(), [2024, 2025, 2026, 2027, 2028]))
        ->toBe(['2024-03-31', '2025-04-20', '2026-04-05', '2027-03-28', '2028-04-16'])
        ->and(Holidays::forYear(2027)['2027-03-15'])->toBe('Καθαρά Δευτέρα (Clean Monday)')
        ->and(Holidays::forYear(2027)['2027-03-04'])->toStartWith('Τσικνοπέμπτη')
        ->and(Holidays::forYear(2027)['2027-06-21'])->toStartWith('Αγίου Πνεύματος (Whit Monday')
        ->and(Holidays::forYear(2027)['2027-05-09'])->toStartWith('Γιορτή της Μητέρας')
        ->and(Holidays::forYear(2027)['2027-10-01'])->not->toContain('Ανεξαρτησία Κύπρου')
        ->and(Holidays::forYear(2027, ['CY'])['2027-10-01'])->toStartWith('Ανεξαρτησία Κύπρου · ')
        ->and(Holidays::inCyprus('Limassol, Cyprus'))->toBeTrue()
        ->and(Holidays::inCyprus('Λεμεσός, Κύπρος'))->toBeTrue()
        ->and(Holidays::inCyprus('Chania, Crete'))->toBeFalse()
        ->and(Holidays::countries(['languages' => ['el', 'en'], 'extra_languages' => ['de'], 'location' => 'Λεμεσός, Κύπρος']))->toBe(['UK', 'IE', 'US', 'DE', 'AT', 'CH', 'CY'])
        ->and(Holidays::countries(['languages' => ['el']]))->toBe([])
        ->and(Holidays::lines(CarbonImmutable::parse('2027-04-30'), CarbonImmutable::parse('2027-05-03')))->toBe([ // no client: Greek and world days only
            '2027-04-30 Μεγάλη Παρασκευή (Good Friday)',
            '2027-05-01 Μεγάλο Σάββατο (Ανάσταση) · Πρωτομαγιά · Skin Cancer Awareness Month, sun safety (all May)',
            '2027-05-02 Πάσχα (Orthodox Easter)',
            '2027-05-03 Δευτέρα του Πάσχα · Αγίου Γεωργίου (Γιώργος, Γεωργία)',
        ])
        ->and(Holidays::lines(CarbonImmutable::parse('2027-04-30'), CarbonImmutable::parse('2027-05-03'), ['UK', 'IE', 'US']))->toBe([
            '2027-04-30 Μεγάλη Παρασκευή (Good Friday)',
            '2027-05-01 Μεγάλο Σάββατο (Ανάσταση) · Πρωτομαγιά · Skin Cancer Awareness Month, sun safety (all May)',
            '2027-05-02 Πάσχα (Orthodox Easter)',
            '2027-05-03 Δευτέρα του Πάσχα · Αγίου Γεωργίου (Γιώργος, Γεωργία) · Early May bank holiday (UK)',
        ])
        ->and(Holidays::lines(CarbonImmutable::parse('2027-05-01'), CarbonImmutable::parse('2027-05-02'), ['ES']))->toBe([
            '2027-05-01 Μεγάλο Σάββατο (Ανάσταση) · Πρωτομαγιά · Labour Day (most of Europe) · Skin Cancer Awareness Month, sun safety (all May)',
            "2027-05-02 Πάσχα (Orthodox Easter) · Mother's Day (ES, PT)",
        ]);
});

test('the European and world days land on the right dates', function () {
    $days = Holidays::forYear(2027, Holidays::countries(['languages' => ['el', 'en'], 'extra_languages' => ['de', 'fr', 'sv']]));

    expect($days['2027-04-23'])->toBe('Παγκόσμια Ημέρα Βιβλίου (World Book Day)') // Saint George waits for Easter Monday in a late-Easter year
        ->and(Holidays::forYear(2026)['2026-04-23'])->toStartWith('Αγίου Γεωργίου')
        ->and($days['2027-03-07'])->toBe('Κυριακή της Απόκρεω · Mothering Sunday (UK)')
        ->and($days['2027-03-28'])->toBe('Easter Sunday (Western) · Clocks go forward (EU summer time)')
        ->and($days['2027-05-06'])->toBe('Ascension Day (DE, FR, NL, BE, AT, CH, Nordics) · Vatertag (DE)')
        ->and($days['2027-05-30'])->toBe('Fête des Mères (FR)')
        ->and($days['2027-06-20'])->toBe("Πεντηκοστή · Γιορτή του Πατέρα · Father's Day (UK, IE, US, FR, NL)") // a late Easter puts Pentecost on Father's Day: both stay
        ->and($days['2027-06-25'])->toBe('Midsummer Eve (SE, FI)')
        ->and($days['2027-09-18'])->toBe('Oktoberfest opens (Munich) · World Cleanup Day')
        ->and($days['2027-09-27'])->toBe('Παγκόσμια Ημέρα Τουρισμού (World Tourism Day)')
        ->and($days['2027-10-31'])->toEndWith('Clocks go back (EU winter time)')
        ->and($days['2027-11-26'])->toBe('Black Friday')
        ->and($days['2027-11-29'])->toBe('Cyber Monday')
        ->and($days['2026-03-13'] ?? Holidays::forYear(2026)['2026-03-13'])->toBe('World Sleep Day')
        ->and(Holidays::forYear(2027)['2027-03-07'])->toBe('Κυριακή της Απόκρεω') // no client: the foreign days stay out
        ->and(Holidays::forYear(2027)['2027-03-28'])->toBe('Clocks go forward (EU summer time)')
        ->and(Holidays::forYear(2027))->not->toHaveKey('2027-05-30');
});

test('the calendar marks holidays and key dates, and the planner is told about the holidays in its window', function () {
    $client = Client::factory()->create(['slug' => 'almyra', 'code' => 'ALMYRA', 'location' => 'Chania, Crete']);
    $client->update(['profile' => ['key_dates' => [['date' => '2027-05-12', 'label' => 'Season opening']]] + $client->profile]);
    $cyprus = Client::factory()->create(['slug' => 'fuse', 'code' => 'FUSE', 'location' => 'Limassol, Cyprus']);
    $cyprus->update(['profile' => ['key_dates' => [['date' => '2027-04-01', 'label' => 'Pharmacy day']]] + $cyprus->profile]);
    $me = User::factory()->create();

    $this->actingAs($me)->get('/calendar?date=2027-05-01')->assertOk()
        ->assertSee('Πάσχα (Orthodox Easter)')->assertSee('ALMYRA: Season opening')->assertDontSee('Ανεξαρτησία Κύπρου')->assertDontSee('Early May bank holiday');
    $this->actingAs($me)->get('/calendar?date=2027-05-01&client=almyra')->assertOk()->assertSee('Season opening')->assertDontSee('ALMYRA: Season')
        ->assertSee('Early May bank holiday (UK)'); // the profile's languages (el, en) bring the UK days
    $this->actingAs($me)->get('/calendar?date=2027-04-01&client=fuse')->assertOk()->assertSee('Εθνική επέτειος Κύπρου')->assertSee('Pharmacy day');

    $request = new PlanRequest($cyprus->toProfileArray(), 3, ['instagram'], CarbonImmutable::parse('2027-09-28'), CarbonImmutable::parse('2027-10-05'));
    expect($request->promptVars()['holidays'])->toContain('2027-09-29 Παγκόσμια Ημέρα Καρδιάς (World Heart Day)')
        ->and(implode("\n", $request->promptVars()['holidays']))->toContain("\n2027-10-01 Ανεξαρτησία Κύπρου · ");
});
