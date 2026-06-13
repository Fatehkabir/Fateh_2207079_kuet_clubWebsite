<?php

function registerTeamMembersForEvent(PDO $pdo, int $eventId, int $teamId): int {
    $mStmt = $pdo->prepare("SELECT member_id FROM team_members WHERE team_id = ?");
    $mStmt->execute([$teamId]);
    $teamMembers = $mStmt->fetchAll(PDO::FETCH_COLUMN);

    $newCount = 0;
    foreach ($teamMembers as $memberId) {
        if (registerMemberForEvent($pdo, $eventId, (int) $memberId)) {
            $newCount++;
        }
    }
    return $newCount;
}


function registerMemberForEvent(PDO $pdo, int $eventId, int $memberId): bool {
    $check = $pdo->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ? AND member_id = ?");
    $check->execute([$eventId, $memberId]);
    if ($check->fetchColumn() > 0) {
        return false;
    }

    $insert = $pdo->prepare("INSERT INTO event_registrations (event_id, member_id, status) VALUES (?, ?, 'joined')");
    $insert->execute([$eventId, $memberId]);
    return true;
}


function registerMemberForTeamEvents(PDO $pdo, int $teamId, int $memberId): array {
    $evStmt = $pdo->prepare("SELECT event_id FROM event_teams WHERE team_id = ?");
    $evStmt->execute([$teamId]);
    $eventIds = $evStmt->fetchAll(PDO::FETCH_COLUMN);

    $registered = [];
    foreach ($eventIds as $eventId) {
        if (registerMemberForEvent($pdo, (int) $eventId, $memberId)) {
            $pdo->prepare("UPDATE events SET seats = seats + 1 WHERE id = ?")->execute([(int) $eventId]);
            $registered[] = (int) $eventId;
        }
    }
    return $registered;
}


function unregisterMemberFromTeamEvents(PDO $pdo, int $teamId, int $memberId): void {
    $evStmt = $pdo->prepare("SELECT event_id FROM event_teams WHERE team_id = ?");
    $evStmt->execute([$teamId]);
    $eventIds = $evStmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($eventIds as $eventId) {
        $del = $pdo->prepare("DELETE FROM event_registrations WHERE event_id = ? AND member_id = ? AND status = 'joined'");
        $del->execute([(int) $eventId, $memberId]);
        if ($del->rowCount() > 0) {
            $pdo->prepare("UPDATE events SET seats = GREATEST(0, seats - 1) WHERE id = ?")->execute([(int) $eventId]);
        }
    }
}


function unregisterTeamFromEvent(PDO $pdo, int $eventId, int $teamId): void {
    $mStmt = $pdo->prepare("SELECT member_id FROM team_members WHERE team_id = ?");
    $mStmt->execute([$teamId]);
    $teamMembers = $mStmt->fetchAll(PDO::FETCH_COLUMN);
    $teamSize = count($teamMembers);

    foreach ($teamMembers as $memberId) {
        $pdo->prepare("DELETE FROM event_registrations WHERE event_id = ? AND member_id = ?")->execute([$eventId, $memberId]);
    }

    if ($teamSize > 0) {
        $pdo->prepare("UPDATE events SET seats = GREATEST(0, seats - ?) WHERE id = ?")->execute([$teamSize, $eventId]);
    }
}
