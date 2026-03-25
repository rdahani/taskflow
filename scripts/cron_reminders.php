#!/usr/bin/env php
<?php
/**
 * Rappels d’échéance (à lancer 1×/jour via cron).
 * Ex. : 0 8 * * * /usr/bin/php /var/www/taskflow/scripts/cron_reminders.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI uniquement.\n");
    exit(1);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo   = getDB();
$today = date('Y-m-d');
$d1    = date('Y-m-d', strtotime('+1 day'));
$d3    = date('Y-m-d', strtotime('+3 days'));

$sql = "SELECT t.id, t.titre, t.date_echeance, u.id AS uid, u.prenom, u.nom
        FROM taches t
        INNER JOIN taches_assignees ta ON ta.tache_id = t.id
        INNER JOIN users u ON u.id = ta.user_id AND u.actif = 1
        WHERE t.statut NOT IN ('termine', 'annule', 'rejete')
        AND t.date_echeance IS NOT NULL
        AND t.date_echeance IN (?, ?, ?)";

$stmt = $pdo->prepare($sql);
$stmt->execute([$today, $d1, $d3]);
$rows = $stmt->fetchAll();

$bucketLabel = [
    'due_today'     => "aujourd'hui",
    'due_tomorrow'  => 'demain',
    'due_in_3d'     => 'dans 3 jours',
];

$ins = $pdo->prepare(
    'INSERT IGNORE INTO reminder_sent (tache_id, user_id, bucket, sent_date) VALUES (?,?,?,?)'
);

$n = 0;
foreach ($rows as $r) {
    $due = $r['date_echeance'];
    if ($due === $today) {
        $bucket = 'due_today';
    } elseif ($due === $d1) {
        $bucket = 'due_tomorrow';
    } else {
        $bucket = 'due_in_3d';
    }

    $ins->execute([(int) $r['id'], (int) $r['uid'], $bucket, $today]);
    if ($ins->rowCount() < 1) {
        continue;
    }

    $when = $bucketLabel[$bucket] ?? $due;
    $title = 'Échéance de tâche';
    $msg   = 'La tâche « ' . $r['titre'] . ' » est prévue ' . $when . ' (' . $due . ').';
    $link  = APP_URL . '/pages/tasks/view.php?id=' . (int) $r['id'];

    createNotification((int) $r['uid'], 'deadline', $title, $msg, $link);
    $n++;
}

fwrite(STDOUT, "TaskFlow rappels : {$n} notification(s) envoyée(s).\n");
exit(0);
