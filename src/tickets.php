<?php
/**
 * PAC actions: a small ticket system.
 *
 * ONE THING, RAISED BY A PERSON, THAT SOMEBODY HAS TO ANSWER. Leave, an award
 * recommendation, a request, a problem - they are all the same shape: who
 * raised it, what about, who it concerns, a thread, and a state. Four separate
 * features would have been four half-finished ones.
 *
 * ONE DOCUMENT PER TICKET - <unit>.ticket.<id> - following the shape the mod
 * already uses for <unit>.opord.<id>. The mod lists only the "role." and
 * "opord." prefixes at boot, so these are invisible to the game until
 * something is written to read them.
 *
 * A MEMBER MAY RAISE AND REPLY, NOT DECIDE. Status changes are an admin's, and
 * that is enforced at the write, not by hiding a button.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/** What somebody can raise, and what each one is for. */
const GHOSTD_TICKET_KINDS = [
    'leave'   => ['label' => 'Leave of absence', 'hint' => 'Time away from operations - dates, and roughly why.'],
    'award'   => ['label' => 'Award recommendation', 'hint' => 'Somebody else deserves recognition. Say who, for what, and when it happened.'],
    'request' => ['label' => 'Request', 'hint' => 'A transfer, a role, a piece of kit, a course.'],
    'issue'   => ['label' => 'Problem', 'hint' => 'Something wrong with the server, the roster, or a mission.'],
];

const GHOSTD_TICKET_STATUSES = [
    'open'     => 'Open',
    'accepted' => 'Accepted',
    'declined' => 'Declined',
    'closed'   => 'Closed',
];

function ghostd_ticket_doc_id(string $id): string
{
    return ghostd_config()['unit'] . '.ticket.' . $id;
}

function ghostd_ticket_prefix(): string
{
    return ghostd_config()['unit'] . '.ticket.';
}

/** Every ticket, newest first. Reads one document per ticket. */
function ghostd_tickets(): array
{
    $prefix = ghostd_ticket_prefix();
    $out = [];
    foreach (ghostd_keys() as $key) {
        if (!str_starts_with($key, $prefix)) {
            continue;
        }
        $doc = ghostd_get($key);
        if (is_array($doc)) {
            $out[] = $doc;
        }
    }
    usort($out, static fn($a, $b) => strcmp((string) ($b['createdAt'] ?? ''), (string) ($a['createdAt'] ?? '')));
    return $out;
}

function ghostd_ticket(string $id): ?array
{
    if (!preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id)) {
        return null;
    }
    $doc = ghostd_get(ghostd_ticket_doc_id($id));
    return is_array($doc) ? $doc : null;
}

/**
 * The next ticket id: TKT-00001 up.
 *
 * Taken from the highest in use rather than a counter, for the same reason the
 * web issues operator ids that way - there is no shared counter to race on,
 * and two admins raising a ticket at the same moment is rare enough that the
 * loser simply retries.
 */
function ghostd_next_ticket_id(): string
{
    $prefix = ghostd_ticket_prefix();
    $max = 0;
    foreach (ghostd_keys() as $key) {
        if (str_starts_with($key, $prefix) && preg_match('/TKT-(\d+)$/', $key, $m)) {
            $max = max($max, (int) $m[1]);
        }
    }
    return sprintf('TKT-%05d', $max + 1);
}

/** May the signed-in person see this ticket at all? */
function ghostd_ticket_visible(array $t): bool
{
    if (ghostd_is_admin()) {
        return true;
    }
    $me = ghostd_self_uid();
    return $me !== '' && (
        (string) ($t['raisedBy'] ?? '') === $me || (string) ($t['about'] ?? '') === $me
    );
}

/**
 * Raise one. Members may, so this opens the write scope after checking - the
 * id is generated here and the raiser is taken from the session, never posted.
 */
function ghostd_ticket_raise(string $kind, string $subject, string $body, string $about = ''): string
{
    $uid = ghostd_self_uid();
    if ($uid === '') {
        throw new RuntimeException('Sign in through Steam to raise a PAC action.');
    }
    if (!isset(GHOSTD_TICKET_KINDS[$kind])) {
        throw new RuntimeException('Pick what kind of action this is.');
    }
    $subject = trim($subject);
    if ($subject === '') {
        throw new RuntimeException('Give it a one-line subject, so a list of these is readable.');
    }
    if ($about !== '' && !preg_match('/^\d{5,20}$/', $about)) {
        throw new RuntimeException('That is not a player uid.');
    }

    $id = ghostd_next_ticket_id();
    $now = gmdate('Y-m-d H:i:s');
    $doc = [
        'section'      => 'ticket',
        'id'           => $id,
        'kind'         => $kind,
        'subject'      => mb_substr($subject, 0, 200),
        'raisedBy'     => $uid,
        'raisedByName' => (string) ($_SESSION['ghostd_name'] ?? ''),
        'about'        => $about,
        'status'       => 'open',
        'createdAt'    => $now,
        'updatedAt'    => $now,
        'replies'      => [[
            'at'     => $now,
            'byUid'  => $uid,
            'byName' => (string) ($_SESSION['ghostd_name'] ?? ''),
            'text'   => mb_substr(trim($body), 0, 8000),
        ]],
    ];

    ghostd_write_scope(true);
    try {
        ghostd_put(ghostd_ticket_doc_id($id), $doc);
    } finally {
        ghostd_write_scope(false);
    }
    return $id;
}

/**
 * Add to the thread, and optionally move the state.
 *
 * A STATUS CHANGE IS AN ADMIN'S. A member replying to their own ticket is a
 * conversation; a member closing it as "accepted" would be deciding their own
 * leave request.
 */
function ghostd_ticket_reply(string $id, string $text, ?string $status = null): void
{
    $t = ghostd_ticket($id);
    if ($t === null) {
        throw new RuntimeException('No such PAC action.');
    }
    if (!ghostd_ticket_visible($t)) {
        throw new RuntimeException('That is not yours to read.');
    }
    $text = trim($text);
    if ($text === '' && $status === null) {
        throw new RuntimeException('Nothing to add.');
    }
    if ($status !== null) {
        if (!ghostd_is_admin()) {
            throw new RuntimeException('Only an admin can change the state of a PAC action.');
        }
        if (!isset(GHOSTD_TICKET_STATUSES[$status])) {
            throw new RuntimeException('Unknown state.');
        }
    }

    $uid = ghostd_self_uid();
    $now = gmdate('Y-m-d H:i:s');
    $replies = is_array($t['replies'] ?? null) ? $t['replies'] : [];
    if ($text !== '' || $status !== null) {
        $replies[] = [
            'at'     => $now,
            'byUid'  => $uid,
            'byName' => (string) ($_SESSION['ghostd_name'] ?? ($uid !== '' ? $uid : 'admin')),
            'text'   => mb_substr($text, 0, 8000),
            'status' => $status,
        ];
    }

    $t['replies']   = $replies;
    $t['updatedAt'] = $now;
    if ($status !== null) {
        $t['status']    = $status;
        $t['decidedBy'] = $uid;
        $t['decidedAt'] = $now;
    }
    unset($t['_id']);

    // A member replying to their own ticket is allowed; the checks above are
    // what make that safe, so the scope opens for both cases.
    ghostd_write_scope(true);
    try {
        ghostd_put(ghostd_ticket_doc_id($id), $t);
    } finally {
        ghostd_write_scope(false);
    }
}
