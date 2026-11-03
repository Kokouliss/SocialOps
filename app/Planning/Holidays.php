<?php

namespace App\Planning;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** Greek public holidays and the days hospitality clients post about, plus Cyprus's own two when the client is there. Orthodox Easter moves; the rest is fixed. */
final class Holidays
{
    /** @return array<string, string> date => label, inside the range, sorted */
    public static function between(CarbonInterface $from, CarbonInterface $to, bool $cyprus = false): array
    {
        $days = [];
        for ($year = $from->year; $year <= $to->year; $year++) {
            $days += self::forYear($year, $cyprus);
        }
        $days = array_filter($days, fn (string $date) => $date >= $from->toDateString() && $date <= $to->toDateString(), ARRAY_FILTER_USE_KEY);
        ksort($days);

        return $days;
    }

    /** @return list<string> "date label" lines for a prompt */
    public static function lines(CarbonInterface $from, CarbonInterface $to, bool $cyprus = false): array
    {
        $days = self::between($from, $to, $cyprus);

        return array_map(fn (string $date, string $label) => "{$date} {$label}", array_keys($days), $days);
    }

    public static function inCyprus(?string $location): bool
    {
        return (bool) preg_match('/cyprus|κ[υύ]προς/iu', (string) $location);
    }

    /** @return array<string, string> */
    public static function forYear(int $year, bool $cyprus = false): array
    {
        $easter = self::orthodoxEaster($year);
        $days = [
            "{$year}-01-01" => 'Πρωτοχρονιά',
            "{$year}-01-06" => 'Θεοφάνεια',
            "{$year}-02-14" => "Valentine's Day",
            $easter->subDays(59)->toDateString() => 'Τσικνοπέμπτη',
            $easter->subDays(48)->toDateString() => 'Καθαρά Δευτέρα (Clean Monday)',
            "{$year}-03-25" => '25η Μαρτίου',
            $easter->subDays(2)->toDateString() => 'Μεγάλη Παρασκευή (Good Friday)',
            $easter->toDateString() => 'Πάσχα (Orthodox Easter)',
            $easter->addDay()->toDateString() => 'Δευτέρα του Πάσχα',
            "{$year}-05-01" => 'Πρωτομαγιά',
            CarbonImmutable::parse("second sunday of may {$year}")->toDateString() => 'Γιορτή της Μητέρας',
            $easter->addDays(50)->toDateString() => 'Αγίου Πνεύματος (Whit Monday)',
            CarbonImmutable::parse("third sunday of june {$year}")->toDateString() => 'Γιορτή του Πατέρα',
            "{$year}-08-15" => 'Δεκαπενταύγουστος',
            "{$year}-10-28" => '28η Οκτωβρίου',
            "{$year}-12-24" => 'Παραμονή Χριστουγέννων',
            "{$year}-12-25" => 'Χριστούγεννα',
            "{$year}-12-26" => 'Δεύτερη μέρα Χριστουγέννων',
            "{$year}-12-31" => 'Παραμονή Πρωτοχρονιάς',
        ];
        if ($cyprus) {
            $days += ["{$year}-04-01" => 'Εθνική επέτειος Κύπρου', "{$year}-10-01" => 'Ανεξαρτησία Κύπρου'];
        }
        ksort($days);

        return $days;
    }

    /** Meeus's Julian algorithm, moved to the Gregorian calendar (13 days, right until 2099). */
    public static function orthodoxEaster(int $year): CarbonImmutable
    {
        $d = (19 * ($year % 19) + 15) % 30;
        $e = (2 * ($year % 4) + 4 * ($year % 7) - $d + 34) % 7;
        $n = $d + $e + 114;

        return CarbonImmutable::create($year, intdiv($n, 31), $n % 31 + 1)->addDays(13);
    }
}
