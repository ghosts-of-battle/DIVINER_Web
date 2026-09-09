<?php
/**
 * Applications to join, and the questions they answer.
 *
 * AN APPLICATION IS TIED TO A STEAM ID, so applying means signing in through
 * Steam first. That is not a hurdle for its own sake: the roster is keyed on
 * Steam ids, so an application that carries a verified one can be turned into
 * a record with nothing retyped and nothing mistyped. It also means one person
 * cannot flood the form, and an admin can see who they are talking to.
 *
 * THE QUESTIONS ARE DATA, NOT CODE. They live in <unit>.web.questions so a
 * unit can ask what it actually wants to know without a deploy. The defaults
 * below are a starting set, used until an admin saves their own.
 *
 * ONE DOCUMENT PER APPLICATION - <unit>.application.<steamid> - following the
 * shape the mod already uses for <unit>.opord.<id>. The mod only ever lists
 * the "role." and "opord." prefixes at boot, so these are invisible to it.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/** The question set a unit gets before anybody edits one. */
const GHOSTD_DEFAULT_QUESTIONS = [
    'callsign'   => ['label' => 'What should we call you?', 'type' => 'text',     'required' => true,  'help' => 'The name you go by in game.'],
    'age'        => ['label' => 'Age',                      'type' => 'text',     'required' => false, 'help' => 'Optional. Some units have a minimum; say if you would rather not.'],
    'timezone'   => ['label' => 'Country or timezone',      'type' => 'text',     'required' => true,  'help' => 'So we know whether our operation times work for you.'],
    'experience' => ['label' => 'Arma experience',          'type' => 'textarea', 'required' => true,  'help' => 'Roughly how many hours, and what you have mostly played - co-op, PvP, editor, modding. Never having played is a fine answer.'],
    'milsim'     => ['label' => 'Milsim experience',        'type' => 'textarea', 'required' => false, 'help' => 'Units you have served with, and roles you have held. Leave empty if this would be your first.'],
    'mic'        => ['label' => 'Working microphone?',      'type' => 'select',   'required' => true,  'help' => '', 'options' => ['Yes', 'No', 'Not yet, but I can get one']],
    'availability' => ['label' => 'When can you play?',     'type' => 'text',     'required' => false, 'help' => 'Which evenings, roughly.'],
    'why'        => ['label' => 'Why us?',                  'type' => 'textarea', 'required' => false, 'help' => 'Optional. What you are looking for in a unit.'],
    'heard'      => ['label' => 'How did you hear about us?', 'type' => 'text',   'required' => false, 'help' => ''],
];

const GHOSTD_QUESTION_TYPES = ['text' => 'Short answer', 'textarea' => 'Long answer', 'select' => 'Choose one'];
const GHOSTD_APPLICATION_STATUSES = ['new' => 'New', 'accepted' => 'Accepted', 'rejected' => 'Rejected'];

function ghostd_questions_doc_id(): string
{
    return ghostd_config()['unit'] . '.web.questions';
}

function ghostd_application_doc_id(string $steamid): string
{
    return ghostd_config()['unit'] . '.application.' . $steamid;
}

/**
 * The questions, in order. Falls back to the defaults when nothing is stored
 * and when the database cannot be read - a form that cannot draw its questions
 * is worse than one asking the standard set.
 */
function ghostd_questions(): array
{
    try {
        $doc = ghostd_get(ghostd_questions_doc_id());
    } catch (Throwable $e) {
        return GHOSTD_DEFAULT_QUESTIONS;
    }
    $items = (is_array($doc['items'] ?? null)) ? $doc['items'] : [];
    if ($items === []) {
        return GHOSTD_DEFAULT_QUESTIONS;
    }

    $out = [];
    foreach ($items as $id => $q) {
        if (!is_array($q)) {
            continue;
        }
        $out[(string) $id] = [
            'label'    => (string) ($q['label'] ?? $id),
            'type'     => isset(GHOSTD_QUESTION_TYPES[(string) ($q['type'] ?? '')]) ? (string) $q['type'] : 'text',
            'required' => !empty($q['required']),
            'help'     => (string) ($q['help'] ?? ''),
            'options'  => array_values(array_filter(array_map('strval', (array) ($q['options'] ?? [])))),
            'order'    => (int) ($q['order'] ?? 0),
        ];
    }
    uasort($out, static fn($a, $b) => $a['order'] <=> $b['order']);
    return $out;
}

/** Admin only - the guard in db.php enforces that, not this. */
function ghostd_questions_save(array $items): void
{
    ghostd_put(ghostd_questions_doc_id(), ['section' => 'web', 'items' => $items]);
}

/**
 * Somebody's application, if they have one.
 */
function ghostd_application(string $steamid): ?array
{
    if ($steamid === '') {
        return null;
    }
    try {
        $doc = ghostd_get(ghostd_application_doc_id($steamid));
    } catch (Throwable $e) {
        return null;
    }
    return is_array($doc) ? $doc : null;
}

/**
 * Save the signed-in person's own application.
 *
 * THE ONLY WRITE A VISITOR MAY MAKE, and it is narrow: the document id is
 * built from the session's Steam id rather than anything posted, so there is
 * no id to tamper with. Everything else on the site stays admin-only.
 */
function ghostd_application_submit(array $answers): void
{
    $uid = ghostd_self_uid();
    if ($uid === '') {
        throw new RuntimeException('Sign in through Steam before applying.');
    }

    $existing = ghostd_application($uid);
    if ($existing !== null && ($existing['status'] ?? 'new') !== 'new') {
        throw new RuntimeException('Your application has already been decided; ask an admin if you want it reopened.');
    }

    $doc = [
        'section'     => 'application',
        'steamId'     => $uid,
        'name'        => (string) ($_SESSION['ghostd_name'] ?? ''),
        'answers'     => $answers,
        'status'      => 'new',
        'submittedAt' => gmdate('Y-m-d H:i:s'),
    ];
    if ($existing !== null) {
        // Keep the first submission date; this is an edit, not a new applicant.
        $doc['submittedAt'] = (string) ($existing['submittedAt'] ?? $doc['submittedAt']);
        $doc['updatedAt']   = gmdate('Y-m-d H:i:s');
    }

    ghostd_write_scope(true);
    try {
        ghostd_put(ghostd_application_doc_id($uid), $doc);
    } finally {
        ghostd_write_scope(false);
    }
}

/** Every application, newest first. Admin pages only. */
function ghostd_applications(): array
{
    $prefix = ghostd_config()['unit'] . '.application.';
    $out = [];
    foreach (ghostd_keys() as $key) {
        if (!str_starts_with($key, $prefix)) {
            continue;
        }
        $doc = ghostd_get($key);
        if (is_array($doc)) {
            $doc['_key'] = $key;
            $out[] = $doc;
        }
    }
    usort($out, static fn($a, $b) => strcmp((string) ($b['submittedAt'] ?? ''), (string) ($a['submittedAt'] ?? '')));
    return $out;
}
