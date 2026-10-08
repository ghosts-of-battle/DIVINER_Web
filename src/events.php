<?php
/**
 * The unit's calendar: operations, training nights, events.
 *
 * ONE DOCUMENT, <unit>.events, a map of items keyed by id - the same shape as
 * ranks or skills, so the Mongo docs page and the backup copy treat it like
 * any other section. An event is a title, a start, a length, which server it
 * runs on, the operation order it briefs (if any), a line of summary, and
 * whether it is public.
 *
 * TIMES ARE STORED IN UTC AND SHOWN IN THE UNIT'S ZONE. The document carries
 * the zone the unit plans in (timezone); the editor takes local times in it
 * and the public feed hands out UTC, so a visitor's browser shows their own.
 * A unit that spans zones still agrees on one instant.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/** The servers an event is offered on; the field takes any other text too. */
const GHOSTD_EVENT_SERVERS = ['Operations', 'Training', 'Events'];

/** How far back the public feed and the page look. */
const GHOSTD_EVENTS_PAST_DAYS = 60;

function ghostd_events_doc_id(): string
{
    return ghostd_config()['unit'] . '.events';
}

function ghostd_event_id_ok(string $id): bool
{
    return (bool) preg_match('/^[a-z0-9][a-z0-9_-]{1,40}$/', $id);
}

function ghostd_timezone_ok(string $tz): bool
{
    return $tz !== '' && in_array($tz, DateTimeZone::listIdentifiers(), true);
}

/** The zone the unit plans in, always a real one. */
function ghostd_events_timezone(): string
{
    try {
        $doc = ghostd_get(ghostd_events_doc_id());
    } catch (Throwable $e) {
        $doc = null;
    }
    $tz = trim((string) ($doc['timezone'] ?? ''));
    return ghostd_timezone_ok($tz) ? $tz : 'UTC';
}

/** One event with every field present and typed. */
function ghostd_event_normalise(array $e, string $id): array
{
    $start = trim((string) ($e['start'] ?? ''));
    $utc = '';
    if ($start !== '') {
        try {
            $utc = (new DateTimeImmutable($start, new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        } catch (Throwable $x) {
            $utc = '';
        }
    }
    return [
        'id'        => $id,
        'title'     => trim((string) ($e['title'] ?? '')),
        'start'     => $utc,
        'minutes'   => max(0, (int) ($e['minutes'] ?? 0)),
        'server'    => trim((string) ($e['server'] ?? '')),
        'opord'     => trim((string) ($e['opord'] ?? '')),
        'summary'   => trim((string) ($e['summary'] ?? '')),
        'public'    => !empty($e['public']),
        'cancelled' => !empty($e['cancelled']),
        'updatedAt' => (string) ($e['updatedAt'] ?? ''),
    ];
}

/**
 * The calendar: ['timezone' => zone, 'items' => [id => event]], soonest first.
 * Never fatal - a visitor's page must open when the database is down.
 */
function ghostd_events(): array
{
    try {
        $doc = ghostd_get(ghostd_events_doc_id());
    } catch (Throwable $e) {
        $doc = null;
    }
    $tz = trim((string) ($doc['timezone'] ?? ''));
    $items = [];
    foreach ((is_array($doc['items'] ?? null) ? $doc['items'] : []) as $id => $e) {
        if (is_array($e) && ghostd_event_id_ok((string) $id)) {
            $items[(string) $id] = ghostd_event_normalise($e, (string) $id);
        }
    }
    uasort($items, static fn($a, $b) => strcmp($a['start'], $b['start']) ?: strcasecmp($a['title'], $b['title']));
    return ['timezone' => ghostd_timezone_ok($tz) ? $tz : 'UTC', 'items' => $items];
}

/** When an event starts, in the given zone (the unit's when none), or null. */
function ghostd_event_when(array $e, ?string $tz = null): ?DateTimeImmutable
{
    if ($e['start'] === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($e['start'], new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone($tz ?? ghostd_events_timezone()));
    } catch (Throwable $x) {
        return null;
    }
}

/** Upcoming (not yet ended) and past, each soonest first. */
function ghostd_events_split(array $items, ?DateTimeImmutable $now = null): array
{
    $now = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $up = [];
    $past = [];
    foreach ($items as $id => $e) {
        $when = ghostd_event_when($e, 'UTC');
        $end = $when === null ? null : $when->modify('+' . max(0, $e['minutes']) . ' minutes');
        if ($end === null || $end >= $now) {
            $up[$id] = $e;
        } else {
            $past[$id] = $e;
        }
    }
    $past = array_reverse($past, true);   // most recent first
    return [$up, $past];
}

/**
 * The form's fields, checked. The date arrives from a datetime-local input,
 * "2026-10-11T19:00", read in the unit's zone and stored as UTC.
 */
function ghostd_event_from_post(array $post, string $tz, string $id): array
{
    $title = trim((string) ($post['title'] ?? ''));
    if ($title === '') {
        throw new RuntimeException('An event needs a title.');
    }
    $when = trim((string) ($post['start'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $when)) {
        throw new RuntimeException('Pick a date and a time.');
    }
    try {
        $utc = (new DateTimeImmutable($when, new DateTimeZone($tz)))->setTimezone(new DateTimeZone('UTC'));
    } catch (Throwable $x) {
        throw new RuntimeException('That date could not be read.');
    }
    $minutes = (int) ($post['minutes'] ?? 0);
    if ($minutes < 0 || $minutes > 24 * 60) {
        throw new RuntimeException('Length is in minutes, up to a day.');
    }
    return [
        'id'        => $id,
        'title'     => mb_substr($title, 0, 120),
        'start'     => $utc->format('Y-m-d\TH:i:s\Z'),
        'minutes'   => $minutes,
        'server'    => mb_substr(trim((string) ($post['server'] ?? '')), 0, 60),
        'opord'     => preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) ($post['opord'] ?? '')))) ?? '',
        'summary'   => mb_substr(trim((string) ($post['summary'] ?? '')), 0, 600),
        'public'    => !empty($post['public']),
        'cancelled' => !empty($post['cancelled']),
        'updatedAt' => gmdate('Y-m-d H:i:s'),
    ];
}

/** A new id: the day it was made and four random hex digits, so a list reads in order. */
function ghostd_event_new_id(): string
{
    return 'ev' . gmdate('Ymd') . '_' . bin2hex(random_bytes(2));
}

/**
 * THE DOCUMENT IS MADE FIRST. ghostd_set_path() matches on the id and does
 * not upsert, so the first event on a unit that never had one would write
 * nothing and say nothing (the same trap the record pictures fell into).
 */
function ghostd_events_ensure(): void
{
    if (ghostd_get(ghostd_events_doc_id()) === null) {
        ghostd_put(ghostd_events_doc_id(), ['section' => 'events', 'timezone' => 'UTC', 'items' => new stdClass()]);
    }
}

function ghostd_events_put(array $item): void
{
    if (!ghostd_event_id_ok($item['id'])) {
        throw new RuntimeException('That is not an event id.');
    }
    ghostd_events_ensure();
    $res = ghostd_set_path(ghostd_events_doc_id(), 'items.' . $item['id'], $item);
    if (($res['matched'] ?? 0) < 1) {
        throw new RuntimeException('The event was not stored: ' . ghostd_events_doc_id() . ' could not be written.');
    }
}

function ghostd_events_delete(string $id): void
{
    if (!ghostd_event_id_ok($id)) {
        throw new RuntimeException('That is not an event id.');
    }
    ghostd_unset_path(ghostd_events_doc_id(), 'items.' . $id);
}

function ghostd_events_set_timezone(string $tz): void
{
    if (!ghostd_timezone_ok($tz)) {
        throw new RuntimeException('That is not a time zone the server knows.');
    }
    ghostd_events_ensure();
    ghostd_set_path(ghostd_events_doc_id(), 'timezone', $tz);
}
