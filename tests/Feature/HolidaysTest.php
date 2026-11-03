<?php

use App\Models\Client;
use App\Models\User;
use App\Planning\Holidays;
use App\Planning\PlanRequest;
use Carbon\CarbonImmutable;

test('Orthodox Easter and the days around it are right for the years that matter', function () {
    expect(array_map(fn ($y) => Holidays::orthodoxEaster($y)->toDateString(), [2024, 2025, 2026, 2027, 2028]))
        ->toBe(['2024-05-05', '2025-04-20', '2026-04-12', '2027-05-02', '2028-04-16'])
        ->and(Holidays::forYear(2027)['2027-03-15'])->toBe('Καθαρά Δευτέρα (Clean Monday)')
        ->and(Holidays::forYear(2027)['2027-03-04'])->toBe('Τσικνοπέμπτη')
        ->and(Holidays::forYear(2027)['2027-06-21'])->toBe('Αγίου Πνεύματος (Whit Monday)')
        ->and(Holidays::forYear(2027)['2027-05-09'])->toBe('Γιορτή της Μητέρας')
        ->and(Holidays::forYear(2027))->not->toHaveKey('2027-10-01')
        ->and(Holidays::forYear(2027, cyprus: true)['2027-10-01'])->toBe('Ανεξαρτησία Κύπρου')
        ->and(Holidays::inCyprus('Limassol, Cyprus'))->toBeTrue()
        ->and(Holidays::inCyprus('Λεμεσός, Κύπρος'))->toBeTrue()
        ->and(Holidays::inCyprus('Chania, Crete'))->toBeFalse()
        ->and(Holidays::lines(CarbonImmutable::parse('2027-04-30'), CarbonImmutable::parse('2027-05-03')))->toBe(['2027-04-30 Μεγάλη Παρασκευή (Good Friday)', '2027-05-01 Πρωτομαγιά', '2027-05-02 Πάσχα (Orthodox Easter)', '2027-05-03 Δευτέρα του Πάσχα']);
});

test('the calendar marks holidays and key dates, and the planner is told about the holidays in its window', function () {
    $client = Client::factory()->create(['slug' => 'almyra', 'code' => 'ALMYRA', 'location' => 'Chania, Crete']);
    $client->update(['profile' => ['key_dates' => [['date' => '2027-05-12', 'label' => 'Season opening']]] + $client->profile]);
    $cyprus = Client::factory()->create(['slug' => 'fuse', 'code' => 'FUSE', 'location' => 'Limassol, Cyprus']);
    $cyprus->update(['profile' => ['key_dates' => [['date' => '2027-04-01', 'label' => 'Pharmacy day']]] + $cyprus->profile]);
    $me = User::factory()->create();

    $this->actingAs($me)->get('/calendar?date=2027-05-01')->assertOk()
        ->assertSee('Πάσχα (Orthodox Easter)')->assertSee('ALMYRA: Season opening')->assertDontSee('Ανεξαρτησία Κύπρου');
    $this->actingAs($me)->get('/calendar?date=2027-05-01&client=almyra')->assertOk()->assertSee('Season opening')->assertDontSee('ALMYRA: Season');
    $this->actingAs($me)->get('/calendar?date=2027-04-01&client=fuse')->assertOk()->assertSee('Εθνική επέτειος Κύπρου')->assertSee('Pharmacy day');

    $request = new PlanRequest($cyprus->toProfileArray(), 3, ['instagram'], CarbonImmutable::parse('2027-09-28'), CarbonImmutable::parse('2027-10-05'));
    expect($request->promptVars()['holidays'])->toBe(['2027-10-01 Ανεξαρτησία Κύπρου']);
});
