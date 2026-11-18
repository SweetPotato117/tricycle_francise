<?php
/** Anniversary terms; clamp leap day to February 28 in a non-leap year. */
function franchiseAnniversary($date) {
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $date);
    if (!$start || $start->format('Y-m-d') !== $date) throw new InvalidArgumentException('Enter a valid franchise issue date.');
    $year = (int) $start->format('Y') + 1;
    $month = (int) $start->format('m');
    $day = min((int) $start->format('d'), (int) $start->setDate($year, $month, 1)->format('t'));
    return $start->setDate($year, $month, $day)->format('Y-m-d');
}
function franchiseRenewalTerm($franchise) {
    $start = !empty($franchise['expiry_date']) ? $franchise['expiry_date'] : date('Y-m-d');
    return ['start' => $start, 'end' => franchiseAnniversary($start), 'year' => (int) substr($start, 0, 4)];
}
