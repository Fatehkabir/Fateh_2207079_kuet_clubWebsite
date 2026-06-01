<?php

require_once 'auth.php';
require_once 'db.php';
requireAdmin();

$adminCheck=$pdo->prepare("SELECT id from members where id = ? and role='admin'");
$adminCheck->execute([$_SESSION['member_id'] ?? 0]);
if(!$adminCheck->fetch()){
    header('Location:logout.php');
    exit();
}

$activeTab=$_GET['tab'] ?? 'members';

$members = $pdo->query("SELECT id, full_name, student_id, email, department, year, role, avatar, created_at, last_login FROM members ORDER BY created_at DESC")->fetchAll();
$messages = $pdo->query("SELECT * FROM contact_messages ORDER BY sent_at DESC LIMIT 100")->fetchAll();
$events = $pdo->query("SELECT e.*, (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) AS reg_count FROM events e ORDER BY e.event_date ASC")->fetchAll();
$announcements = $pdo->query("SELECT a.*, m.full_name AS author_name FROM announcements a LEFT JOIN members m ON a.created_by = m.id ORDER BY a.created_at DESC")->fetchAll();
$teams = $pdo->query("SELECT t.*, m.full_name AS creator_name, (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) AS member_count FROM teams t LEFT JOIN members m ON t.created_by = m.id ORDER BY t.created_at DESC")->fetchAll();
$allMembers = $pdo->query("SELECT id, full_name, avatar, department FROM members ORDER BY full_name ASC")->fetchAll();


$stats = [
    'members'  => $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn(),
    'messages' => $pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn(),
    'events'   => $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn(),
    'regs'     => $pdo->query("SELECT COUNT(*) FROM event_registrations")->fetchColumn(),
    'anncs'    => $pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn(),
    'teams'    => $pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn(),
];

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])){
    header('Content-Type: application/json');
    $action=$_POST['action'];

    if($action==='toggle_role'){
        $mid=intval($_POST['member_id']);
        if($mid !== $_SESSION['member_id'] ){
            $pdo->prepare("UPDATE members SET role = if(role='admin','member','admin') where id = ?")->execute([$mid]);
            echo json_encode(['success'=>true]);
        } else{
            echo json_encode(['success'=>false, 'message'=>'You cannot change your own role.']);
        }

    } elseif($action==='delete_member'){
        $mid=intval($_POST['member_id']);
        if($mid != $_SESSION['member_id']){
            $pdo->prepare("DELETE FROM members WHERE id = ?")->execute([$mid]);
            echo json_encode(['success'=>true]);
        } else{
            echo json_encode(['success'=>false, 'message'=>'You cannot delete your own account.']);
        }

    } elseif ($action === 'delete_message') {
        $msgId = intval($_POST['message_id']);
        $pdo->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([$msgId]);
        echo json_encode(['success' => true]);

    } elseif ($action === 'mark_read') {
        $msgId = intval($_POST['msg_id']);
        $pdo->prepare("UPDATE member_messages SET is_read = 1 WHERE id = ?")->execute([$msgId]);
        echo json_encode(['success' => true]); 

    } elseif ($action === 'delete_member_message') {
        $msgId = intval($_POST['msg_id']);
        $pdo->prepare("DELETE FROM member_messages WHERE id = ?")->execute([$msgId]);
        echo json_encode(['success' => true]);
   
    } elseif ($action === 'mark_all_read') {
        $pdo->exec("UPDATE member_messages SET is_read = 1");
        echo json_encode(['success' => true]);

    } elseif ($action === 'add_event') {
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $event_date  = $_POST['event_date'] ?? '';
        $start_time  = $_POST['start_time'] ?? '';
        $end_time    = $_POST['end_time'] ?? '';
        $location    = trim($_POST['location'] ?? '');
        $seats       = max(0, intval($_POST['seats'] ?? 50));
        if ($title && $event_date && $start_time && $end_time && $location) {
            $pdo->prepare("INSERT INTO events (title,description,event_date,start_time,end_time,location,seats,created_by) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$title,$description,$event_date,$start_time,$end_time,$location,$seats,$_SESSION['member_id']]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } else { echo json_encode(['success' => false, 'message' => 'Fill all required fields.']); }

    } elseif ($action === 'edit_event') {
        $id          = intval($_POST['event_id']);
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $event_date  = $_POST['event_date'] ?? '';
        $start_time  = $_POST['start_time'] ?? '';
        $end_time    = $_POST['end_time'] ?? '';
        $location    = trim($_POST['location'] ?? '');
        $seats       = max(0, intval($_POST['seats'] ?? 50));
        if ($title && $event_date && $start_time && $end_time && $location && $id > 0) {
            $pdo->prepare("UPDATE events SET title=?,description=?,event_date=?,start_time=?,end_time=?,location=?,seats=? WHERE id=?")
                ->execute([$title,$description,$event_date,$start_time,$end_time,$location,$seats,$id]);
            echo json_encode(['success' => true]);
        } else { echo json_encode(['success' => false, 'message' => 'Invalid data.']); }

    } elseif ($action === 'delete_event') {
        $id = intval($_POST['event_id']);
        $pdo->prepare("DELETE FROM events WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);

    } elseif ($action === 'assign_team_to_event') {
        $eventId = intval($_POST['event_id']);
        $teamId  = intval($_POST['team_id']);
        if ($eventId > 0 && $teamId > 0) {
            $insertStmt = $pdo->prepare("INSERT IGNORE INTO event_teams (event_id, team_id) VALUES (?,?)");
            $insertStmt->execute([$eventId, $teamId]);
            $isNewAssignment = $insertStmt->rowCount() > 0;

            $mStmt = $pdo->prepare("SELECT member_id FROM team_members WHERE team_id = ?");
            $mStmt->execute([$teamId]);
            $teamMembers = $mStmt->fetchAll(PDO::FETCH_COLUMN);
            $teamSize = count($teamMembers);

            
            $newRegistrations = registerTeamMembersForEvent($pdo, $eventId, $teamId);

            if ($isNewAssignment && $teamSize > 0) {
                $pdo->prepare("UPDATE events SET seats = seats + ? WHERE id = ?")->execute([$teamSize, $eventId]);
            } elseif (!$isNewAssignment && $newRegistrations > 0) {
                $pdo->prepare("UPDATE events SET seats = seats + ? WHERE id = ?")->execute([$newRegistrations, $eventId]);
            }

            if (!$isNewAssignment && $newRegistrations === 0 && $teamSize > 0) {
                echo json_encode(['success' => false, 'message' => 'Team already assigned and all members are registered.']);
                exit();
            }

            $t = $pdo->prepare("SELECT name FROM teams WHERE id=?"); $t->execute([$teamId]);
            $row = $t->fetch();
            $stats = $pdo->prepare("SELECT e.seats, (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) AS reg_count FROM events e WHERE e.id = ?");
            $stats->execute([$eventId]);
            $seatInfo = $stats->fetch();
            echo json_encode([
                'success'           => true,
                'name'              => $row['name'],
                'seats'             => (int) $seatInfo['seats'],
                'reg_count'         => (int) $seatInfo['reg_count'],
                'team_size'         => $teamSize,
                'members_registered'=> $newRegistrations,
            ]);
        } else { echo json_encode(['success' => false, 'message' => 'Invalid data.']); }

    } elseif ($action === 'unassign_team_from_event') {
        $eventId = intval($_POST['event_id']);
        $teamId  = intval($_POST['team_id']);
        if ($eventId > 0 && $teamId > 0) {
            unregisterTeamFromEvent($pdo, $eventId, $teamId);
            $pdo->prepare("DELETE FROM event_teams WHERE event_id=? AND team_id=?")->execute([$eventId, $teamId]);

            $stats = $pdo->prepare("SELECT e.seats, (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) AS reg_count FROM events e WHERE e.id = ?");
            $stats->execute([$eventId]);
            $seatInfo = $stats->fetch();
            echo json_encode([
                'success'   => true,
                'seats'     => (int) $seatInfo['seats'],
                'reg_count' => (int) $seatInfo['reg_count'],
            ]);
        } else { echo json_encode(['success' => false, 'message' => 'Invalid data.']); }

    } elseif ($action === 'get_event_teams') {
        $eventId = intval($_POST['event_id']);
        $stmt = $pdo->prepare("SELECT t.id, t.name FROM event_teams et JOIN teams t ON et.team_id = t.id WHERE et.event_id = ?");
        $stmt->execute([$eventId]);
        echo json_encode(['success' => true, 'teams' => $stmt->fetchAll()]);

    } elseif ($action === 'add_announcement') {
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if ($title && $content) {
            $pdo->prepare("INSERT INTO announcements (title,content,created_by) VALUES (?,?,?)")
                ->execute([$title,$content,$_SESSION['member_id']]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } else { echo json_encode(['success' => false, 'message' => 'Title and content required.']); }

    } elseif ($action === 'edit_announcement') {
        $id      = intval($_POST['ann_id']);
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if ($id > 0 && $title && $content) {
            $pdo->prepare("UPDATE announcements SET title=?,content=? WHERE id=?")->execute([$title,$content,$id]);
            echo json_encode(['success' => true]);
        } else { echo json_encode(['success' => false, 'message' => 'Invalid data.']); }

    } elseif ($action === 'delete_announcement') {
        $id = intval($_POST['ann_id']);
        $pdo->prepare("DELETE FROM announcements WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);

    } elseif ($action === 'add_team') {
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($name) {
            $pdo->prepare("INSERT INTO teams (name,description,created_by) VALUES (?,?,?)")
                ->execute([$name,$description,$_SESSION['member_id']]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } else { echo json_encode(['success' => false, 'message' => 'Team name required.']); }

    } elseif ($action === 'edit_team') {
        $id          = intval($_POST['team_id']);
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($id > 0 && $name) {
            $pdo->prepare("UPDATE teams SET name=?,description=? WHERE id=?")->execute([$name,$description,$id]);
            echo json_encode(['success' => true]);
        } else { echo json_encode(['success' => false, 'message' => 'Invalid data.']); }

    } elseif ($action === 'delete_team') {
        $id = intval($_POST['team_id']);
        $pdo->prepare("DELETE FROM teams WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);

    } elseif ($action === 'add_team_member') {
        $teamId   = intval($_POST['team_id']);
        $memberId = intval($_POST['member_id']);
        if ($teamId > 0 && $memberId > 0) {
            try {
                $insert = $pdo->prepare("INSERT IGNORE INTO team_members (team_id,member_id) VALUES (?,?)");
                $insert->execute([$teamId, $memberId]);
                if ($insert->rowCount() === 0) {
                    echo json_encode(['success' => false, 'message' => 'Already a member.']);
                    exit();
                }

              
                $joinedEvents = registerMemberForTeamEvents($pdo, $teamId, $memberId);

                $m = $pdo->prepare("SELECT full_name,avatar FROM members WHERE id=?"); $m->execute([$memberId]);
                $row = $m->fetch();
                echo json_encode([
                    'success'       => true,
                    'name'          => $row['full_name'],
                    'avatar'        => $row['avatar'],
                    'events_joined' => count($joinedEvents),
                ]);
            } catch(Exception $e) { echo json_encode(['success' => false, 'message' => 'Could not add member.']); }
        } else { echo json_encode(['success' => false, 'message' => 'Invalid data.']); }

    } elseif ($action === 'remove_team_member') {
        $teamId   = intval($_POST['team_id']);
        $memberId = intval($_POST['member_id']);
        unregisterMemberFromTeamEvents($pdo, $teamId, $memberId);
        $pdo->prepare("DELETE FROM team_members WHERE team_id=? AND member_id=?")->execute([$teamId,$memberId]);
        echo json_encode(['success' => true]);

    } elseif ($action === 'get_team_members') {
        $teamId = intval($_POST['team_id']);
        $stmt = $pdo->prepare("SELECT m.id, m.full_name, m.avatar, m.department FROM team_members tm JOIN members m ON tm.member_id = m.id WHERE tm.team_id = ?");
        $stmt->execute([$teamId]);
        echo json_encode(['success' => true, 'members' => $stmt->fetchAll()]);

    } else { echo json_encode(['success' => false, 'message' => 'Unknown action.']); }
    exit();
}

$adminName   = explode(' ', $_SESSION['member_name'])[0];
$adminAvatar = $_SESSION['member_avatar'] ?? '⚙️';






?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel | HACK Club KUET</title>
    <meta name="description" content="HACK Club admin panel — manage members, messages, events, announcements, and teams.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="myClub.css">
    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>

                .admin-page { padding: 110px 6% 80px; min-height: 100vh; }
        .admin-topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; flex-wrap:wrap; gap:15px; }
        .admin-topbar h1 { font-size:2rem; font-weight:800; color:#fff; }
        .admin-topbar h1 span { color:#00f3ff; }

        /* ── Stats Row ───────────────────────────────────────── */
        .admin-stats-row { display:grid; grid-template-columns:repeat(6,1fr); gap:14px; margin-bottom:30px; }
        .admin-stat-card { padding:20px 16px; border-radius:16px; background:rgba(10,15,25,0.7); border:1px solid rgba(0,243,255,0.12); display:flex; align-items:center; gap:12px; transition:0.3s; }
        .admin-stat-card:hover { border-color:rgba(0,243,255,0.4); transform:translateY(-2px); }
        .asc-icon { width:44px; height:44px; border-radius:12px; background:rgba(0,243,255,0.1); border:1px solid rgba(0,243,255,0.2); display:flex; align-items:center; justify-content:center; font-size:1.3rem; color:#00f3ff; flex-shrink:0; }
        .asc-num   { font-size:1.7rem; font-weight:900; color:#fff; line-height:1; margin-bottom:2px; }
        .asc-label { font-size:0.68rem; text-transform:uppercase; letter-spacing:1.5px; color:#64748b; font-weight:700; }

        /* ── Tabs ────────────────────────────────────────────── */
        .tabs { display:flex; gap:4px; margin-bottom:24px; border-bottom:1px solid rgba(255,255,255,0.07); flex-wrap:wrap; }
        .tab-btn { padding:11px 16px; border-radius:10px 10px 0 0; background:none; border:none; border-bottom:3px solid transparent; color:#64748b; font-size:0.82rem; font-weight:700; font-family:'Outfit',sans-serif; cursor:pointer; display:flex; align-items:center; gap:6px; transition:0.2s; letter-spacing:0.3px; text-transform:uppercase; position:relative; }
        .tab-btn:hover { color:#cbd5e1; }
        .tab-btn.active { color:#00f3ff; border-bottom-color:#00f3ff; }
        .tab-content { display:none; }
        .tab-content.active { display:block; }
        .tab-badge { background:#ff4d4d; color:#fff; border-radius:20px; padding:1px 7px; font-size:0.65rem; font-weight:900; position:absolute; top:6px; right:4px; }

        /* ── Tables ──────────────────────────────────────────── */
        .admin-table-wrap { background:rgba(10,15,25,0.6); border:1px solid rgba(0,243,255,0.15); border-radius:20px; overflow:hidden; }
        .admin-table { width:100%; border-collapse:collapse; font-size:0.88rem; }
        .admin-table thead th { padding:14px 18px; text-align:left; font-size:0.7rem; text-transform:uppercase; letter-spacing:1.5px; color:#64748b; font-weight:700; border-bottom:1px solid rgba(255,255,255,0.07); background:rgba(5,8,16,0.5); }
        .admin-table tbody tr { border-bottom:1px solid rgba(255,255,255,0.04); transition:background 0.2s; }
        .admin-table tbody tr:last-child { border-bottom:none; }
        .admin-table tbody tr:hover { background:rgba(0,243,255,0.04); }
        .admin-table tbody td { padding:12px 18px; color:#cbd5e1; vertical-align:middle; }
        .member-cell { display:flex; align-items:center; gap:10px; }
        .mem-avatar { font-size:1.5rem; }
        .mem-name { font-weight:700; color:#fff; font-size:0.9rem; }
        .mem-email { font-size:0.75rem; color:#64748b; }
        .role-badge { padding:3px 10px; border-radius:20px; font-size:0.7rem; font-weight:700; letter-spacing:0.5px; text-transform:uppercase; }
        .role-admin  { background:rgba(0,243,255,0.12); color:#00f3ff; border:1px solid rgba(0,243,255,0.3); }
        .role-member { background:rgba(255,255,255,0.05); color:#64748b; border:1px solid rgba(255,255,255,0.1); }

        /* ── Buttons ─────────────────────────────────────────── */
        .tbl-action-btn { padding:7px 13px; border-radius:8px; font-size:0.76rem; font-weight:700; font-family:'Outfit',sans-serif; cursor:pointer; border:1px solid; transition:0.2s; display:inline-flex; align-items:center; gap:5px; margin-right:4px; text-decoration:none; }
        .btn-toggle  { background:rgba(0,243,255,0.08);  color:#00f3ff;  border-color:rgba(0,243,255,0.3); }
        .btn-toggle:hover  { background:rgba(0,243,255,0.2); }
        .btn-danger  { background:rgba(255,77,77,0.08);   color:#ff6b6b;  border-color:rgba(255,77,77,0.3); }
        .btn-danger:hover  { background:rgba(255,77,77,0.2); }
        .btn-success { background:rgba(0,229,160,0.08);   color:#00e5a0;  border-color:rgba(0,229,160,0.3); }
        .btn-success:hover { background:rgba(0,229,160,0.2); }
        .btn-warn    { background:rgba(255,165,0,0.08);   color:#ffa500;  border-color:rgba(255,165,0,0.3); }
        .btn-warn:hover    { background:rgba(255,165,0,0.2); }
        .btn-add-main { display:inline-flex; align-items:center; gap:8px; padding:11px 22px; background:linear-gradient(135deg,#00f3ff,#2d72fc); color:white; font-weight:700; border-radius:12px; border:none; cursor:pointer; font-family:'Outfit',sans-serif; font-size:0.9rem; transition:0.3s; box-shadow:0 0 20px rgba(0,243,255,0.3); text-decoration:none; }
        .btn-add-main:hover { transform:translateY(-2px); box-shadow:0 0 30px rgba(0,243,255,0.5); }

        /* ── Tab header row ──────────────────────────────────── */
        .tab-header-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
        .tab-header-row h2 { font-size:1.3rem; font-weight:800; color:#fff; display:flex; align-items:center; gap:10px; }

        /* ── Events Grid ─────────────────────────────────────── */
        .events-admin-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:18px; }
        .ev-admin-card { padding:22px; border-radius:16px; background:rgba(10,15,25,0.6); border:1px solid rgba(0,243,255,0.15); transition:0.3s; }
        .ev-admin-card:hover { border-color:rgba(0,243,255,0.35); }
        .ev-admin-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; gap:10px; }
        .ev-admin-title  { font-size:1.02rem; font-weight:800; color:#fff; flex:1; }
        .ev-admin-date   { background:rgba(0,243,255,0.1); border:1px solid rgba(0,243,255,0.25); border-radius:10px; padding:6px 10px; text-align:center; font-size:0.68rem; color:#00f3ff; font-weight:800; letter-spacing:1px; text-transform:uppercase; flex-shrink:0; }
        .ev-admin-date strong { display:block; font-size:1.3rem; color:#fff; line-height:1; }
        .ev-admin-meta   { font-size:0.78rem; color:#64748b; margin-bottom:4px; display:flex; align-items:center; gap:5px; }
        .ev-admin-desc   { color:#64748b; font-size:0.83rem; line-height:1.5; margin-bottom:14px; }
        .ev-seats-bar    { background:rgba(255,255,255,0.06); border-radius:10px; height:5px; overflow:hidden; margin-bottom:6px; }
        .ev-seats-fill   { height:100%; border-radius:10px; background:linear-gradient(90deg,#00f3ff,#2d72fc); transition:width 0.4s; }
        .ev-seats-label  { font-size:0.75rem; color:#64748b; font-weight:600; margin-bottom:14px; }
        .ev-teams-row    { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:14px; min-height:24px; }
        .ev-team-chip    { background:rgba(45,114,252,0.12); border:1px solid rgba(45,114,252,0.3); border-radius:20px; padding:3px 10px; font-size:0.72rem; font-weight:700; color:#7aa2f7; display:flex; align-items:center; gap:5px; }
        .ev-team-chip .rm-team { color:#ff6b6b; cursor:pointer; background:none; border:none; font-size:0.9rem; padding:0; margin-left:2px; line-height:1; }

        /* ── Announcements ───────────────────────────────────── */
        .ann-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:18px; }
        .ann-card { padding:22px; border-radius:16px; background:rgba(10,15,25,0.6); border:1px solid rgba(0,243,255,0.15); transition:0.3s; }
        .ann-card:hover { border-color:rgba(0,243,255,0.35); }
        .ann-card-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px; gap:10px; }
        .ann-card-title  { font-size:1.05rem; font-weight:800; color:#fff; flex:1; }
        .ann-card-meta   { font-size:0.75rem; color:#64748b; margin-bottom:10px; display:flex; align-items:center; gap:5px; }
        .ann-card-body   { color:#94a3b8; font-size:0.88rem; line-height:1.6; }
        .ann-badge       { background:rgba(0,243,255,0.08); color:#00f3ff; border:1px solid rgba(0,243,255,0.25); border-radius:20px; padding:2px 10px; font-size:0.7rem; font-weight:700; letter-spacing:0.5px; flex-shrink:0; }

        /* ── Teams Grid ──────────────────────────────────────── */
        .teams-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:18px; }
        .team-card { padding:22px; border-radius:16px; background:rgba(10,15,25,0.6); border:1px solid rgba(0,243,255,0.15); transition:0.3s; }
        .team-card:hover { border-color:rgba(0,243,255,0.35); }
        .team-card-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px; gap:10px; }
        .team-card-title  { font-size:1.05rem; font-weight:800; color:#fff; flex:1; }
        .team-card-desc   { color:#64748b; font-size:0.85rem; line-height:1.5; margin-bottom:14px; }
        .team-member-chips { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:14px; min-height:28px; }
        .team-chip { display:flex; align-items:center; gap:5px; background:rgba(0,243,255,0.07); border:1px solid rgba(0,243,255,0.18); border-radius:20px; padding:4px 10px; font-size:0.75rem; font-weight:600; color:#cbd5e1; }
        .team-chip .remove-chip { color:#ff6b6b; cursor:pointer; font-size:1rem; line-height:1; background:none; border:none; padding:0; margin-left:2px; }
        .team-count-badge { background:rgba(255,255,255,0.06); color:#64748b; border:1px solid rgba(255,255,255,0.1); border-radius:20px; padding:2px 10px; font-size:0.7rem; font-weight:700; flex-shrink:0; }

        /* ── Messages ────────────────────────────────────────── */
        .msg-cards { display:flex; flex-direction:column; gap:12px; }
        .msg-card { padding:18px 20px; border-radius:14px; background:rgba(10,15,25,0.6); border:1px solid rgba(255,255,255,0.08); transition:0.2s; }
        .msg-card.unread { border-color:rgba(0,243,255,0.25); background:rgba(0,243,255,0.03); }
        .msg-card:hover { border-color:rgba(0,243,255,0.3); }
        .msg-card-top { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap; margin-bottom:8px; }
        .msg-sender { display:flex; align-items:center; gap:10px; }
        .msg-sender-avatar { font-size:1.5rem; }
        .msg-sender-name { font-weight:700; color:#fff; font-size:0.9rem; }
        .msg-sender-email { font-size:0.72rem; color:#64748b; }
        .msg-subject { font-size:0.9rem; font-weight:700; color:#00f3ff; margin-bottom:6px; }
        .msg-body { color:#94a3b8; font-size:0.85rem; line-height:1.6; white-space:pre-wrap; }
        .msg-meta { font-size:0.72rem; color:#475569; }
        .unread-dot { width:8px; height:8px; border-radius:50%; background:#00f3ff; display:inline-block; margin-right:6px; flex-shrink:0; }

        /* ── Modal Overlay ───────────────────────────────────── */
        .modal-overlay { display:none; position:fixed; inset:0; z-index:5000; background:rgba(0,0,0,0.7); backdrop-filter:blur(8px); align-items:center; justify-content:center; }
        .modal-overlay.open { display:flex; animation:fadeIn 0.2s ease; }
        .modal-box { background:rgba(10,15,25,0.98); border:1px solid rgba(0,243,255,0.25); border-radius:24px; padding:36px; max-width:540px; width:94%; box-shadow:0 25px 60px rgba(0,0,0,0.7); position:relative; max-height:90vh; overflow-y:auto; }
        .modal-box h2 { font-size:1.4rem; font-weight:800; color:#fff; margin-bottom:24px; display:flex; align-items:center; gap:10px; }
        .modal-box h2 i { color:#00f3ff; }
        .modal-close { position:absolute; top:18px; right:18px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); color:#64748b; border-radius:10px; width:36px; height:36px; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:1.2rem; transition:0.2s; }
        .modal-close:hover { color:#ff6b6b; border-color:rgba(255,77,77,0.4); }

        /* ── Form Controls ───────────────────────────────────── */
        .form-group { margin-bottom:18px; }
        .form-group label { display:block; font-size:0.78rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#64748b; margin-bottom:8px; }
        .form-group input, .form-group textarea, .form-group select { width:100%; padding:12px 16px; background:rgba(5,8,16,0.8); border:1px solid rgba(0,243,255,0.2); border-radius:12px; color:#fff; font-family:'Outfit',sans-serif; font-size:0.9rem; transition:0.2s; outline:none; box-sizing:border-box; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color:#00f3ff; box-shadow:0 0 0 3px rgba(0,243,255,0.1); }
        .form-group textarea { resize:vertical; min-height:90px; }
        .form-group select option { background:#0a0f19; }
        .form-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .modal-footer { display:flex; justify-content:flex-end; gap:10px; margin-top:8px; }
        .btn-cancel { padding:11px 22px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); color:#94a3b8; border-radius:12px; cursor:pointer; font-family:'Outfit',sans-serif; font-size:0.9rem; font-weight:700; transition:0.2s; }
        .btn-cancel:hover { background:rgba(255,255,255,0.1); }
        .btn-submit-modal { padding:11px 28px; background:linear-gradient(135deg,#00f3ff,#2d72fc); border:none; color:white; border-radius:12px; cursor:pointer; font-family:'Outfit',sans-serif; font-size:0.9rem; font-weight:700; transition:0.2s; }
        .btn-submit-modal:hover { opacity:0.85; }

        /* ── Toast ───────────────────────────────────────────── */
        .toast-admin { position:fixed; bottom:30px; right:30px; padding:14px 22px; border-radius:14px; font-size:0.9rem; font-weight:600; display:none; z-index:9999; animation:fadeIn 0.3s ease; max-width:340px; line-height:1.4; }
        .toast-admin.success { background:rgba(0,229,160,0.15); border:1px solid rgba(0,229,160,0.4); color:#00e5a0; }
        .toast-admin.error   { background:rgba(255,77,77,0.12);  border:1px solid rgba(255,77,77,0.4);  color:#ff8080; }
        .msg-text { max-width:280px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:#94a3b8; }

        .msg-view-modal .modal-box { max-width:640px; }

        @keyframes fadeIn { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
        @media (max-width:1100px) { .admin-stats-row { grid-template-columns:repeat(3,1fr); } }
        @media (max-width:700px) { .admin-stats-row { grid-template-columns:repeat(2,1fr); } .form-row-2 { grid-template-columns:1fr; } }
    
    </style>
    </head>

    <body>
    <div class="bg-grid"></div>

    <nav class="navbar">
        <a href="index.php" class="logo">HACK<span class="dot">.</span></a>
        <ul>
            <li><a href="index.php">HOME</a></li>
            <li><a href="dashboard.php">DASHBOARD</a></li>
        </ul>
        <div class="nav-user-menu">
            <div class="nav-avatar" id="navAvatarBtn" onclick="toggleUserDropdown()">
                <span class="avatar-emoji"><?= htmlspecialchars($adminAvatar) ?></span>
                <span class="avatar-name"><?= htmlspecialchars($adminName) ?></span>
                <i class='bx bx-chevron-down'></i>
            </div>
            <div class="user-dropdown glass-card" id="userDropdown">
                <div class="dropdown-header">
                    <span class="d-avatar"><?= htmlspecialchars($adminAvatar) ?></span>
                    <div>
                        <div class="d-name"><?= htmlspecialchars($_SESSION['member_name']) ?></div>
                        <div class="d-role">Administrator</div>
                    </div>
                </div>
                <a href="logout.php" class="dropdown-item logout-item"><i class='bx bx-log-out'></i> Logout</a>
            </div>
        </div>
    </nav>

        <div class="admin-page">

        <div class="admin-topbar">
            <h1>Admin <span>Panel</span></h1>
            <a href="dashboard.php" class="btn-add-main" style="background:rgba(255,255,255,0.08);box-shadow:none;border:1px solid rgba(255,255,255,0.1);color:#cbd5e1;">
                <i class='bx bx-arrow-back'></i> Dashboard
            </a>
        </div>

                <div class="admin-stats-row">
            <div class="admin-stat-card">
                <div class="asc-icon"><i class='bx bx-group'></i></div>
                <div><div class="asc-num"><?= $stats['members'] ?></div><div class="asc-label">Members</div></div>
            </div>
            <div class="admin-stat-card">
                <div class="asc-icon"><i class='bx bx-calendar'></i></div>
                <div><div class="asc-num"><?= $stats['events'] ?></div><div class="asc-label">Events</div></div>
            </div>
            <div class="admin-stat-card">
                <div class="asc-icon"><i class='bx bx-check-circle'></i></div>
                <div><div class="asc-num"><?= $stats['regs'] ?></div><div class="asc-label">Registrations</div></div>
            </div>
            <div class="admin-stat-card">
                <div class="asc-icon"><i class='bx bx-megaphone'></i></div>
                <div><div class="asc-num"><?= $stats['anncs'] ?></div><div class="asc-label">Announcements</div></div>
            </div>
            <div class="admin-stat-card">
                <div class="asc-icon"><i class='bx bx-shield'></i></div>
                <div><div class="asc-num"><?= $stats['teams'] ?></div><div class="asc-label">Teams</div></div>
            </div>
            <div class="admin-stat-card">
                <div class="asc-icon"><i class='bx bx-envelope'></i></div>
                <div><div class="asc-num"><?= $stats['messages'] ?></div><div class="asc-label">Messages</div></div>
            </div>
        </div>

                <div class="tabs">
            <button class="tab-btn <?= $activeTab==='members'       ?'active':'' ?>" onclick="switchTab('members')"><i class='bx bx-group'></i>Members</button>
            <button class="tab-btn <?= $activeTab==='events'        ?'active':'' ?>" onclick="switchTab('events')"><i class='bx bx-calendar'></i>Events</button>
            <button class="tab-btn <?= $activeTab==='announcements' ?'active':'' ?>" onclick="switchTab('announcements')"><i class='bx bx-megaphone'></i>Announcements</button>
            <button class="tab-btn <?= $activeTab==='teams'         ?'active':'' ?>" onclick="switchTab('teams')"><i class='bx bx-shield'></i>Teams</button>
            <button class="tab-btn <?= $activeTab==='messages'      ?'active':'' ?>" onclick="switchTab('messages')" id="tabBtnMessages">
                <i class='bx bx-envelope'></i>Inbox
                <?php if ($unreadMsgs > 0): ?>
                <span class="tab-badge" id="inboxBadge"><?= $unreadMsgs ?></span>
                <?php endif; ?>
            </button>
            <button class="tab-btn <?= $activeTab==='contact'       ?'active':'' ?>" onclick="switchTab('contact')"><i class='bx bx-comment-detail'></i>Contact Msgs</button>
        </div>

                <div id="tab-members" class="tab-content <?= $activeTab==='members'?'active':'' ?>">
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Member</th><th>Student ID</th><th>Dept</th><th>Year</th><th>Role</th><th>Joined</th><th>Last Login</th><th>Actions</th></tr></thead>
                    <tbody id="membersTableBody">
                    <?php foreach ($members as $m): ?>
                    <tr id="member-row-<?= $m['id'] ?>">
                        <td>
                            <div class="member-cell">
                                <span class="mem-avatar"><?= htmlspecialchars($m['avatar']) ?></span>
                                <div>
                                    <div class="mem-name"><?= htmlspecialchars($m['full_name']) ?></div>
                                    <div class="mem-email"><?= htmlspecialchars($m['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($m['student_id']) ?></td>
                        <td><?= htmlspecialchars($m['department'] ?? '—') ?></td>
                        <td><?= $m['year'] ? $m['year'].'Y' : '—' ?></td>
                        <td><span class="role-badge role-<?= $m['role'] ?>" id="role-badge-<?= $m['id'] ?>"><?= ucfirst($m['role']) ?></span></td>
                        <td><?= date('M j, Y', strtotime($m['created_at'])) ?></td>
                        <td style="font-size:0.78rem;color:#64748b;"><?= $m['last_login'] ? date('M j H:i', strtotime($m['last_login'])) : 'Never' ?></td>
                        <td>
                            <?php if ($m['id'] !== $_SESSION['member_id']): ?>
                            <button class="tbl-action-btn btn-toggle" onclick="toggleRole(<?= $m['id'] ?>)"><i class='bx bx-transfer'></i> Role</button>
                            <button class="tbl-action-btn btn-danger"  onclick="deleteMember(<?= $m['id'] ?>)"><i class='bx bx-trash'></i></button>
                            <?php else: ?>
                            <span style="color:#475569;font-size:0.8rem;">You</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

             <div id="tab-events" class="tab-content <?= $activeTab==='events'?'active':'' ?>">
            <div class="tab-header-row">
                <h2><i class='bx bx-calendar' style="color:#00f3ff;"></i> Events</h2>
                <button class="btn-add-main" onclick="openModal('modalAddEvent')"><i class='bx bx-plus'></i> Add Event</button>
            </div>
            <div class="events-admin-grid" id="eventsGrid">
            <?php foreach ($events as $ev):
                $pct = $ev['seats'] > 0 ? min(100, round(($ev['reg_count'] / $ev['seats']) * 100)) : 0;
                $evTeamsStmt = $pdo->prepare("SELECT t.id, t.name FROM event_teams et JOIN teams t ON et.team_id = t.id WHERE et.event_id = ?");
                $evTeamsStmt->execute([$ev['id']]);
                $evTeams = $evTeamsStmt->fetchAll();
            ?>
            <div class="ev-admin-card" id="ev-card-<?= $ev['id'] ?>">
                <div class="ev-admin-header">
                    <div class="ev-admin-title"><?= htmlspecialchars($ev['title']) ?></div>
                    <div class="ev-admin-date">
                        <?= htmlspecialchars($ev['event_month']) ?>
                        <strong><?= htmlspecialchars($ev['event_day']) ?></strong>
                    </div>
                </div>
                <div class="ev-admin-meta"><i class='bx bx-map-pin'></i><?= htmlspecialchars($ev['location']) ?></div>
                <div class="ev-admin-meta"><i class='bx bx-time'></i><?= date('g:i A', strtotime($ev['start_time'])) ?> – <?= date('g:i A', strtotime($ev['end_time'])) ?></div>
                <div class="ev-admin-desc"><?= htmlspecialchars($ev['description']) ?></div>
                <div class="ev-seats-bar"><div class="ev-seats-fill" id="ev-seats-fill-<?= $ev['id'] ?>" style="width:<?= $pct ?>%"></div></div>
                <div class="ev-seats-label" id="ev-seats-label-<?= $ev['id'] ?>"><?= $ev['reg_count'] ?> / <?= $ev['seats'] ?> seats (<?= $pct ?>%)</div>
                <!-- Teams assigned to this event -->
                <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#475569;margin-bottom:6px;">Participating Teams</div>
                <div class="ev-teams-row" id="ev-teams-<?= $ev['id'] ?>">
                    <?php foreach ($evTeams as $et): ?>
                    <span class="ev-team-chip" id="ev-team-chip-<?= $ev['id'] ?>-<?= $et['id'] ?>">
                        🛡️ <?= htmlspecialchars($et['name']) ?>
                        <button class="rm-team" onclick="unassignTeamFromEvent(<?= $ev['id'] ?>, <?= $et['id'] ?>)" title="Remove team">×</button>
                    </span>
                    <?php endforeach; ?>
                    <?php if (empty($evTeams)): ?>
                    <span style="color:#475569;font-size:0.78rem;" id="ev-no-team-<?= $ev['id'] ?>">No teams assigned</span>
                    <?php endif; ?>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button class="tbl-action-btn btn-success" onclick="openAssignTeam(<?= $ev['id'] ?>, <?= htmlspecialchars(json_encode($ev['title'])) ?>)">
                        <i class='bx bx-shield-plus'></i> Add Team
                    </button>
                    <button class="tbl-action-btn btn-toggle"
                        onclick="openEditEvent(<?= $ev['id'] ?>, <?= htmlspecialchars(json_encode($ev['title'])) ?>, <?= htmlspecialchars(json_encode($ev['description'])) ?>, '<?= $ev['event_date'] ?>', '<?= $ev['start_time'] ?>', '<?= $ev['end_time'] ?>', <?= htmlspecialchars(json_encode($ev['location'])) ?>, <?= $ev['seats'] ?>)">
                        <i class='bx bx-edit-alt'></i> Edit
                    </button>
                    <button class="tbl-action-btn btn-danger" onclick="deleteEvent(<?= $ev['id'] ?>)">
                        <i class='bx bx-trash'></i> Delete
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($events)): ?>
            <p style="color:#475569;grid-column:1/-1;text-align:center;padding:60px 0;">No events yet. Click <strong>Add Event</strong> to create one.</p>
            <?php endif; ?>
            </div>
        </div>

        <div id="tab-announcements" class="tab-content <?= $activeTab==='announcements'?'active':'' ?>">
            <div class="tab-header-row">
                <h2><i class='bx bx-megaphone' style="color:#00f3ff;"></i> Announcements</h2>
                <button class="btn-add-main" onclick="openModal('modalAddAnn')"><i class='bx bx-plus'></i> New Announcement</button>
            </div>
            <div class="ann-grid" id="annGrid">
            <?php foreach ($announcements as $ann): ?>
            <div class="ann-card" id="ann-card-<?= $ann['id'] ?>">
                <div class="ann-card-header">
                    <div class="ann-card-title"><?= htmlspecialchars($ann['title']) ?></div>
                    <span class="ann-badge">📢 Active</span>
                </div>
                <div class="ann-card-meta">
                    <i class='bx bx-user-circle'></i> <?= htmlspecialchars($ann['author_name'] ?? 'Admin') ?>
                    &nbsp;·&nbsp; <i class='bx bx-calendar'></i> <?= date('M j, Y', strtotime($ann['created_at'])) ?>
                </div>
                <div class="ann-card-body"><?= nl2br(htmlspecialchars($ann['content'])) ?></div>
                <div style="margin-top:14px;display:flex;gap:8px;">
                    <button class="tbl-action-btn btn-toggle"
                        onclick="openEditAnn(<?= $ann['id'] ?>, <?= htmlspecialchars(json_encode($ann['title'])) ?>, <?= htmlspecialchars(json_encode($ann['content'])) ?>)">
                        <i class='bx bx-edit-alt'></i> Edit
                    </button>
                    <button class="tbl-action-btn btn-danger" onclick="deleteAnn(<?= $ann['id'] ?>)">
                        <i class='bx bx-trash'></i> Delete
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($announcements)): ?>
            <p style="color:#475569;grid-column:1/-1;text-align:center;padding:60px 0;">No announcements yet.</p>
            <?php endif; ?>
            </div>
        </div>

         <div id="tab-teams" class="tab-content <?= $activeTab==='teams'?'active':'' ?>">
            <div class="tab-header-row">
                <h2><i class='bx bx-shield' style="color:#00f3ff;"></i> Teams</h2>
                <button class="btn-add-main" onclick="openModal('modalAddTeam')"><i class='bx bx-plus'></i> New Team</button>
            </div>
            <div class="teams-grid" id="teamsGrid">
            <?php foreach ($teams as $team):
                $tmStmt = $pdo->prepare("SELECT m.id, m.full_name, m.avatar FROM team_members tm JOIN members m ON tm.member_id=m.id WHERE tm.team_id=?");
                $tmStmt->execute([$team['id']]);
                $teamMems = $tmStmt->fetchAll();
            ?>
            <div class="team-card" id="team-card-<?= $team['id'] ?>">
                <div class="team-card-header">
                    <div class="team-card-title"><?= htmlspecialchars($team['name']) ?></div>
                    <span class="team-count-badge" id="team-count-<?= $team['id'] ?>"><?= $team['member_count'] ?> members</span>
                </div>
                <div class="team-card-desc"><?= htmlspecialchars($team['description'] ?: 'No description.') ?></div>
                <div class="team-member-chips" id="team-chips-<?= $team['id'] ?>">
                    <?php foreach ($teamMems as $tm): ?>
                    <span class="team-chip" id="chip-<?= $team['id'] ?>-<?= $tm['id'] ?>">
                        <?= htmlspecialchars($tm['avatar']) ?> <?= htmlspecialchars($tm['full_name']) ?>
                        <button class="remove-chip" onclick="removeTeamMember(<?= $team['id'] ?>, <?= $tm['id'] ?>)" title="Remove">×</button>
                    </span>
                    <?php endforeach; ?>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button class="tbl-action-btn btn-success" onclick="openAddMemberToTeam(<?= $team['id'] ?>, <?= htmlspecialchars(json_encode($team['name'])) ?>)">
                        <i class='bx bx-user-plus'></i> Add Member
                    </button>
                    <button class="tbl-action-btn btn-toggle"
                        onclick="openEditTeam(<?= $team['id'] ?>, <?= htmlspecialchars(json_encode($team['name'])) ?>, <?= htmlspecialchars(json_encode($team['description'] ?? '')) ?>)">
                        <i class='bx bx-edit-alt'></i> Edit
                    </button>
                    <button class="tbl-action-btn btn-danger" onclick="deleteTeam(<?= $team['id'] ?>)">
                        <i class='bx bx-trash'></i> Delete
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($teams)): ?>
            <p style="color:#475569;grid-column:1/-1;text-align:center;padding:60px 0;">No teams yet. Create one!</p>
            <?php endif; ?>
            </div>
        </div>

 <div id="tab-messages" class="tab-content <?= $activeTab==='messages'?'active':'' ?>">
            <div class="tab-header-row">
                <h2><i class='bx bx-envelope' style="color:#00f3ff;"></i> Member Inbox
                    <?php if ($unreadMsgs > 0): ?>
                    <span style="background:#00f3ff;color:#000;font-size:0.75rem;padding:2px 10px;border-radius:20px;font-weight:900;"><?= $unreadMsgs ?> new</span>
                    <?php endif; ?>
                </h2>
                <?php if ($unreadMsgs > 0): ?>
                <button class="tbl-action-btn btn-toggle" onclick="markAllRead()"><i class='bx bx-check-double'></i> Mark All Read</button>
                <?php endif; ?>
            </div>
            <?php if (empty($memberMsgs)): ?>
            <p style="color:#475569;text-align:center;padding:60px 0;">No messages from members yet.</p>
            <?php else: ?>
            <div class="msg-cards" id="memberMsgList">
                <?php foreach ($memberMsgs as $mm): ?>
                <div class="msg-card <?= !$mm['is_read'] ? 'unread' : '' ?>" id="mmsg-<?= $mm['id'] ?>">
                    <div class="msg-card-top">
                        <div class="msg-sender">
                            <?php if (!$mm['is_read']): ?><span class="unread-dot"></span><?php endif; ?>
                            <span class="msg-sender-avatar"><?= htmlspecialchars($mm['avatar']) ?></span>
                            <div>
                                <div class="msg-sender-name"><?= htmlspecialchars($mm['full_name']) ?></div>
                                <div class="msg-sender-email"><?= htmlspecialchars($mm['email']) ?> · <?= htmlspecialchars($mm['department'] ?? '') ?></div>
                            </div>
                        </div>
                        <div class="msg-meta"><?= date('M j, Y H:i', strtotime($mm['sent_at'])) ?></div>
                    </div>
                    <div class="msg-subject"><?= htmlspecialchars($mm['subject']) ?></div>
                    <div class="msg-body"><?= htmlspecialchars($mm['message']) ?></div>
                    <div style="margin-top:12px;display:flex;gap:8px;">
                        <?php if (!$mm['is_read']): ?>
                        <button class="tbl-action-btn btn-success" onclick="markRead(<?= $mm['id'] ?>)"><i class='bx bx-check'></i> Mark Read</button>
                        <?php endif; ?>
                        <button class="tbl-action-btn btn-danger" onclick="deleteMemberMsg(<?= $mm['id'] ?>)"><i class='bx bx-trash'></i> Delete</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div id="tab-contact" class="tab-content <?= $activeTab==='contact'?'active':'' ?>">
            <div class="tab-header-row"><h2><i class='bx bx-comment-detail' style="color:#00f3ff;"></i> Contact Form Messages</h2></div>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Name</th><th>Email</th><th>Message</th><th>IP</th><th>Date</th><th>Action</th></tr></thead>
                    <tbody id="messagesTableBody">
                    <?php if (empty($messages)): ?>
                    <tr><td colspan="6" style="text-align:center;color:#475569;padding:40px;">No messages yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                    <tr id="msg-row-<?= $msg['id'] ?>">
                        <td style="font-weight:700;color:#fff;"><?= htmlspecialchars($msg['name']) ?></td>
                        <td><?= htmlspecialchars($msg['email']) ?></td>
                        <td><div class="msg-text" title="<?= htmlspecialchars($msg['message']) ?>"><?= htmlspecialchars($msg['message']) ?></div></td>
                        <td style="font-family:monospace;font-size:0.78rem;color:#64748b;"><?= htmlspecialchars($msg['ip_address'] ?? '—') ?></td>
                        <td style="font-size:0.82rem;"><?= date('M j, Y H:i', strtotime($msg['sent_at'])) ?></td>
                        <td><button class="tbl-action-btn btn-danger" onclick="deleteMessage(<?= $msg['id'] ?>)"><i class='bx bx-trash'></i></button></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div class="modal-overlay" id="modalAddEvent">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal('modalAddEvent')"><i class='bx bx-x'></i></button>
            <h2><i class='bx bx-calendar-plus'></i> Add Event</h2>
            <form id="formAddEvent">
                <div class="form-group"><label>Title *</label><input type="text" id="ae_title" placeholder="Event title" required></div>
                <div class="form-group"><label>Description</label><textarea id="ae_desc" placeholder="Event description…"></textarea></div>
                <div class="form-row-2">
                    <div class="form-group"><label>Date *</label><input type="date" id="ae_date" required></div>
                    <div class="form-group"><label>Seats</label><input type="number" id="ae_seats" min="1" value="50"></div>
                </div>
                <div class="form-row-2">
                    <div class="form-group"><label>Start Time *</label><input type="time" id="ae_start" required></div>
                    <div class="form-group"><label>End Time *</label><input type="time" id="ae_end" required></div>
                </div>
                <div class="form-group"><label>Location *</label><input type="text" id="ae_loc" placeholder="Room / Building" required></div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('modalAddEvent')">Cancel</button>
                    <button type="submit" class="btn-submit-modal"><i class='bx bx-save'></i> Create Event</button>
                </div>
            </form>
        </div>
    </div>

  
    <div class="modal-overlay" id="modalEditEvent">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal('modalEditEvent')"><i class='bx bx-x'></i></button>
            <h2><i class='bx bx-edit-alt'></i> Edit Event</h2>
            <form id="formEditEvent">
                <input type="hidden" id="ee_id">
                <div class="form-group"><label>Title *</label><input type="text" id="ee_title" required></div>
                <div class="form-group"><label>Description</label><textarea id="ee_desc"></textarea></div>
                <div class="form-row-2">
                    <div class="form-group"><label>Date *</label><input type="date" id="ee_date" required></div>
                    <div class="form-group"><label>Seats</label><input type="number" id="ee_seats" min="1"></div>
                </div>
                <div class="form-row-2">
                    <div class="form-group"><label>Start Time *</label><input type="time" id="ee_start" required></div>
                    <div class="form-group"><label>End Time *</label><input type="time" id="ee_end" required></div>
                </div>
                <div class="form-group"><label>Location *</label><input type="text" id="ee_loc" required></div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('modalEditEvent')">Cancel</button>
                    <button type="submit" class="btn-submit-modal"><i class='bx bx-save'></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

   
    <div class="modal-overlay" id="modalAssignTeam">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal('modalAssignTeam')"><i class='bx bx-x'></i></button>
            <h2><i class='bx bx-shield-plus'></i> Assign Team to <span id="at_eventName" style="color:#00f3ff;"></span></h2>
            <input type="hidden" id="at_eventId">
            <div class="form-group">
                <label>Select Team</label>
                <select id="at_teamId">
                    <option value="">— Choose a team —</option>
                    <?php foreach ($allTeams as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('modalAssignTeam')">Cancel</button>
                <button type="button" class="btn-submit-modal" onclick="submitAssignTeam()"><i class='bx bx-shield-plus'></i> Assign</button>
            </div>
        </div>
    </div>

  
    <div class="modal-overlay" id="modalAddAnn">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal('modalAddAnn')"><i class='bx bx-x'></i></button>
            <h2><i class='bx bx-megaphone'></i> New Announcement</h2>
            <form id="formAddAnn">
                <div class="form-group"><label>Title *</label><input type="text" id="aa_title" placeholder="Announcement title" required></div>
                <div class="form-group"><label>Content *</label><textarea id="aa_content" rows="5" placeholder="Write your announcement…" required></textarea></div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('modalAddAnn')">Cancel</button>
                    <button type="submit" class="btn-submit-modal"><i class='bx bx-send'></i> Publish</button>
                </div>
            </form>
        </div>
    </div>


    <div class="modal-overlay" id="modalEditAnn">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal('modalEditAnn')"><i class='bx bx-x'></i></button>
            <h2><i class='bx bx-edit-alt'></i> Edit Announcement</h2>
            <form id="formEditAnn">
                <input type="hidden" id="ea_id">
                <div class="form-group"><label>Title *</label><input type="text" id="ea_title" required></div>
                <div class="form-group"><label>Content *</label><textarea id="ea_content" rows="5" required></textarea></div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('modalEditAnn')">Cancel</button>
                    <button type="submit" class="btn-submit-modal"><i class='bx bx-save'></i> Save</button>
                </div>
            </form>
        </div>
    </div>


    <div class="modal-overlay" id="modalAddTeam">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal('modalAddTeam')"><i class='bx bx-x'></i></button>
            <h2><i class='bx bx-shield-plus'></i> New Team</h2>
            <form id="formAddTeam">
                <div class="form-group"><label>Team Name *</label><input type="text" id="at_name" placeholder="e.g. Robotics Core" required></div>
                <div class="form-group"><label>Description</label><textarea id="at_desc" placeholder="What does this team do?"></textarea></div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('modalAddTeam')">Cancel</button>
                    <button type="submit" class="btn-submit-modal"><i class='bx bx-save'></i> Create Team</button>
                </div>
            </form>
        </div>
    </div>

    
    <div class="modal-overlay" id="modalEditTeam">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal('modalEditTeam')"><i class='bx bx-x'></i></button>
            <h2><i class='bx bx-edit-alt'></i> Edit Team</h2>
            <form id="formEditTeam">
                <input type="hidden" id="et_id">
                <div class="form-group"><label>Team Name *</label><input type="text" id="et_name" required></div>
                <div class="form-group"><label>Description</label><textarea id="et_desc"></textarea></div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('modalEditTeam')">Cancel</button>
                    <button type="submit" class="btn-submit-modal"><i class='bx bx-save'></i> Save</button>
                </div>
            </form>
        </div>
    </div>

  
    <div class="modal-overlay" id="modalAddTeamMember">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal('modalAddTeamMember')"><i class='bx bx-x'></i></button>
            <h2><i class='bx bx-user-plus'></i> Add Member to <span id="atm_teamName" style="color:#00f3ff;"></span></h2>
            <input type="hidden" id="atm_teamId">
            <div class="form-group">
                <label>Select Member</label>
                <select id="atm_memberId">
                    <option value="">— Choose a member —</option>
                    <?php foreach ($allMembers as $m): ?>
                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['avatar'].' '.$m['full_name']) ?> <?= $m['department'] ? '('.$m['department'].')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('modalAddTeamMember')">Cancel</button>
                <button type="button" class="btn-submit-modal" onclick="submitAddTeamMember()"><i class='bx bx-user-check'></i> Add</button>
            </div>
        </div>
    </div>

    <div id="adminToast" class="toast-admin"></div>

    <script>
   
    function openModal(id)  { document.getElementById(id).classList.add('open'); }
    function closeModal(id) { document.getElementById(id).classList.remove('open'); }
    document.querySelectorAll('.modal-overlay').forEach(o => {
        o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
    });

    function switchTab(name) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + name).classList.add('active');
        document.querySelectorAll('.tab-btn').forEach(b => {
            if (b.getAttribute('onclick') && b.getAttribute('onclick').includes("'" + name + "'")) b.classList.add('active');
        });
    }


    function toggleUserDropdown() { document.getElementById('userDropdown').classList.toggle('show'); }
    document.addEventListener('click', e => {
        const btn = document.getElementById('navAvatarBtn');
        const dd  = document.getElementById('userDropdown');
        if (btn && !btn.contains(e.target)) dd.classList.remove('show');
    });


    function showAdminToast(msg, type) {
        const t = document.getElementById('adminToast');
        t.textContent = msg;
        t.className = 'toast-admin ' + type;
        t.style.display = 'block';
        clearTimeout(t._to);
        t._to = setTimeout(() => t.style.display = 'none', 3800);
    }

    function adminAction(data, onSuccess, onFail) {
        fetch('admin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(data).toString()
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) { if (onSuccess) onSuccess(d); showAdminToast('✅ Done!', 'success'); }
            else { showAdminToast('❌ ' + (d.message || 'Action failed.'), 'error'); if (onFail) onFail(d); }
        })
        .catch(() => showAdminToast('❌ Network error.', 'error'));
    }


    function toggleRole(memberId) {
        adminAction({ action: 'toggle_role', member_id: memberId }, function() {
            const badge = document.getElementById('role-badge-' + memberId);
            if (badge) {
                const isAdmin = badge.textContent.trim().toLowerCase() === 'admin';
                badge.textContent = isAdmin ? 'Member' : 'Admin';
                badge.className = 'role-badge role-' + (isAdmin ? 'member' : 'admin');
            }
        });
    }
    function deleteMember(memberId) {
        if (!confirm('Delete this member permanently? All their data will be removed.')) return;
        adminAction({ action: 'delete_member', member_id: memberId }, function() {
            const row = document.getElementById('member-row-' + memberId);
            if (row) row.remove();
        });
    }


    document.getElementById('formAddEvent').addEventListener('submit', function(e) {
        e.preventDefault();
        adminAction({
            action: 'add_event',
            title:       document.getElementById('ae_title').value,
            description: document.getElementById('ae_desc').value,
            event_date:  document.getElementById('ae_date').value,
            start_time:  document.getElementById('ae_start').value,
            end_time:    document.getElementById('ae_end').value,
            location:    document.getElementById('ae_loc').value,
            seats:       document.getElementById('ae_seats').value
        }, function() {
            closeModal('modalAddEvent');
            setTimeout(() => location.reload(), 1000);
        });
    });

    function openEditEvent(id, title, desc, date, start, end, loc, seats) {
        document.getElementById('ee_id').value    = id;
        document.getElementById('ee_title').value = title;
        document.getElementById('ee_desc').value  = desc;
        document.getElementById('ee_date').value  = date;
        document.getElementById('ee_start').value = start.substring(0,5);
        document.getElementById('ee_end').value   = end.substring(0,5);
        document.getElementById('ee_loc').value   = loc;
        document.getElementById('ee_seats').value = seats;
        openModal('modalEditEvent');
    }
    document.getElementById('formEditEvent').addEventListener('submit', function(e) {
        e.preventDefault();
        adminAction({
            action:      'edit_event',
            event_id:    document.getElementById('ee_id').value,
            title:       document.getElementById('ee_title').value,
            description: document.getElementById('ee_desc').value,
            event_date:  document.getElementById('ee_date').value,
            start_time:  document.getElementById('ee_start').value,
            end_time:    document.getElementById('ee_end').value,
            location:    document.getElementById('ee_loc').value,
            seats:       document.getElementById('ee_seats').value
        }, function() {
            closeModal('modalEditEvent');
            setTimeout(() => location.reload(), 900);
        });
    });
    function deleteEvent(id) {
        if (!confirm('Delete this event? All registrations will also be removed.')) return;
        adminAction({ action: 'delete_event', event_id: id }, function() {
            const card = document.getElementById('ev-card-' + id);
            if (card) card.remove();
        });
    }

    function updateEventSeats(eventId, regCount, seats) {
        const pct = seats > 0 ? Math.min(100, Math.round((regCount / seats) * 100)) : 0;
        const fill = document.getElementById('ev-seats-fill-' + eventId);
        const label = document.getElementById('ev-seats-label-' + eventId);
        if (fill) fill.style.width = pct + '%';
        if (label) label.textContent = regCount + ' / ' + seats + ' seats (' + pct + '%)';
    }


    function openAssignTeam(eventId, eventName) {
        document.getElementById('at_eventId').value = eventId;
        document.getElementById('at_eventName').textContent = eventName;
        document.getElementById('at_teamId').value = '';
        openModal('modalAssignTeam');
    }
    function submitAssignTeam() {
        const eventId = document.getElementById('at_eventId').value;
        const teamId  = document.getElementById('at_teamId').value;
        if (!teamId) { showAdminToast('Please select a team.', 'error'); return; }
        adminAction({ action: 'assign_team_to_event', event_id: eventId, team_id: teamId }, function(d) {
            closeModal('modalAssignTeam');
            const row = document.getElementById('ev-teams-' + eventId);
            if (row) {
                const noTeam = document.getElementById('ev-no-team-' + eventId);
                if (noTeam) noTeam.remove();
                const chipId = 'ev-team-chip-' + eventId + '-' + teamId;
                if (!document.getElementById(chipId)) {
                    const chip = document.createElement('span');
                    chip.className = 'ev-team-chip';
                    chip.id = chipId;
                    chip.innerHTML = '🛡️ ' + d.name + ' <button class="rm-team" onclick="unassignTeamFromEvent(' + eventId + ',' + teamId + ')" title="Remove">×</button>';
                    row.appendChild(chip);
                }
            }
            if (typeof d.reg_count !== 'undefined' && typeof d.seats !== 'undefined') {
                updateEventSeats(eventId, d.reg_count, d.seats);
            }
        });
    }
    function unassignTeamFromEvent(eventId, teamId) {
        if (!confirm('Remove this team from the event?')) return;
        adminAction({ action: 'unassign_team_from_event', event_id: eventId, team_id: teamId }, function(d) {
            const chip = document.getElementById('ev-team-chip-' + eventId + '-' + teamId);
            if (chip) chip.remove();
            if (typeof d.reg_count !== 'undefined' && typeof d.seats !== 'undefined') {
                updateEventSeats(eventId, d.reg_count, d.seats);
            }
        });
    }

  
        e.preventDefault();
        adminAction({
            action:  'add_announcement',
            title:   document.getElementById('aa_title').value,
            content: document.getElementById('aa_content').value
        }, function() {
            closeModal('modalAddAnn');
            setTimeout(() => location.reload(), 900);
        });
    });
    function openEditAnn(id, title, content) {
        document.getElementById('ea_id').value      = id;
        document.getElementById('ea_title').value   = title;
        document.getElementById('ea_content').value = content;
        openModal('modalEditAnn');
    }
    document.getElementById('formEditAnn').addEventListener('submit', function(e) {
        e.preventDefault();
        adminAction({
            action:  'edit_announcement',
            ann_id:  document.getElementById('ea_id').value,
            title:   document.getElementById('ea_title').value,
            content: document.getElementById('ea_content').value
        }, function() {
            closeModal('modalEditAnn');
            setTimeout(() => location.reload(), 900);
        });
    });
    function deleteAnn(id) {
        if (!confirm('Delete this announcement?')) return;
        adminAction({ action: 'delete_announcement', ann_id: id }, function() {
            const card = document.getElementById('ann-card-' + id);
            if (card) card.remove();
        });
    }


    document.getElementById('formAddTeam').addEventListener('submit', function(e) {
        e.preventDefault();
        adminAction({
            action:      'add_team',
            name:        document.getElementById('at_name').value,
            description: document.getElementById('at_desc').value
        }, function() {
            closeModal('modalAddTeam');
            setTimeout(() => location.reload(), 900);
        });
    });
    function openEditTeam(id, name, desc) {
        document.getElementById('et_id').value   = id;
        document.getElementById('et_name').value = name;
        document.getElementById('et_desc').value = desc;
        openModal('modalEditTeam');
    }
    document.getElementById('formEditTeam').addEventListener('submit', function(e) {
        e.preventDefault();
        adminAction({
            action:      'edit_team',
            team_id:     document.getElementById('et_id').value,
            name:        document.getElementById('et_name').value,
            description: document.getElementById('et_desc').value
        }, function() {
            closeModal('modalEditTeam');
            setTimeout(() => location.reload(), 900);
        });
    });
    function deleteTeam(id) {
        if (!confirm('Delete this team? All member assignments will be removed.')) return;
        adminAction({ action: 'delete_team', team_id: id }, function() {
            const card = document.getElementById('team-card-' + id);
            if (card) card.remove();
        });
    }
    function openAddMemberToTeam(teamId, teamName) {
        document.getElementById('atm_teamId').value          = teamId;
        document.getElementById('atm_teamName').textContent  = teamName;
        document.getElementById('atm_memberId').value        = '';
        openModal('modalAddTeamMember');
    }
    function submitAddTeamMember() {
        const teamId   = document.getElementById('atm_teamId').value;
        const memberId = document.getElementById('atm_memberId').value;
        if (!memberId) { showAdminToast('Please select a member.', 'error'); return; }
        adminAction({ action: 'add_team_member', team_id: teamId, member_id: memberId }, function(d) {
            closeModal('modalAddTeamMember');
            const chips = document.getElementById('team-chips-' + teamId);
            if (chips) {
                const chip = document.createElement('span');
                chip.className = 'team-chip';
                chip.id = 'chip-' + teamId + '-' + memberId;
                chip.innerHTML = d.avatar + ' ' + d.name + ' <button class="remove-chip" onclick="removeTeamMember(' + teamId + ',' + memberId + ')" title="Remove">×</button>';
                chips.appendChild(chip);
            }
            const badge = document.getElementById('team-count-' + teamId);
            if (badge) badge.textContent = (parseInt(badge.textContent) + 1) + ' members';
            if (d.events_joined > 0) {
                showAdminToast('✅ Member added and auto-joined ' + d.events_joined + ' event(s).', 'success');
            }
        });
    }
    function removeTeamMember(teamId, memberId) {
        if (!confirm('Remove this member from the team?')) return;
        adminAction({ action: 'remove_team_member', team_id: teamId, member_id: memberId }, function() {
            const chip = document.getElementById('chip-' + teamId + '-' + memberId);
            if (chip) chip.remove();
            const badge = document.getElementById('team-count-' + teamId);
            if (badge) badge.textContent = Math.max(0, parseInt(badge.textContent) - 1) + ' members';
        });
    }

    function markRead(msgId) {
        adminAction({ action: 'mark_read', msg_id: msgId }, function() {
            const card = document.getElementById('mmsg-' + msgId);
            if (card) {
                card.classList.remove('unread');
                const dot = card.querySelector('.unread-dot');
                if (dot) dot.remove();
                const btn = card.querySelector('button[onclick*="markRead"]');
                if (btn) btn.remove();
            }
            updateInboxBadge(-1);
        });
    }
    function markAllRead() {
        adminAction({ action: 'mark_all_read' }, function() {
            document.querySelectorAll('.msg-card.unread').forEach(c => {
                c.classList.remove('unread');
                const dot = c.querySelector('.unread-dot');
                if (dot) dot.remove();
                const btn = c.querySelector('button[onclick*="markRead"]');
                if (btn) btn.remove();
            });
            const badge = document.getElementById('inboxBadge');
            if (badge) badge.remove();
        });
    }
    function updateInboxBadge(delta) {
        const badge = document.getElementById('inboxBadge');
        if (!badge) return;
        let count = parseInt(badge.textContent) + delta;
        if (count <= 0) badge.remove();
        else badge.textContent = count;
    }
    function deleteMemberMsg(msgId) {
        if (!confirm('Delete this message?')) return;
        adminAction({ action: 'delete_member_message', msg_id: msgId }, function() {
            const card = document.getElementById('mmsg-' + msgId);
            if (card) card.remove();
        });
    }


    function deleteMessage(msgId) {
        adminAction({ action: 'delete_message', message_id: msgId }, function() {
            const row = document.getElementById('msg-row-' + msgId);
            if (row) row.remove();
        });
    }
    </script>
</body>
</html>
