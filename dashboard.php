<?php
require_once 'auth.php';
require_once 'db.php';
require_once 'team_helpers.php';
requireLogin();

$memberId = $_SESSION['member_id'];

$stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$memberId]);
$member = $stmt->fetch();

if (!$member) { header('Location: logout.php'); exit(); }

$teamIdsStmt = $pdo->prepare("SELECT team_id FROM team_members WHERE member_id = ?");
$teamIdsStmt->execute([$memberId]);
foreach ($teamIdsStmt->fetchAll(PDO::FETCH_COLUMN) as $teamId) {
    registerMemberForTeamEvents($pdo, (int) $teamId, (int) $memberId);
}

$regStmt = $pdo->prepare("
    SELECT e.id, e.title, e.event_month, e.event_day, e.event_date, e.location, er.registered_at, er.status
    FROM event_registrations er
    JOIN events e ON er.event_id = e.id
    WHERE er.member_id = ?
    ORDER BY e.event_date ASC
");
$regStmt->execute([$memberId]);
$myEvents = $regStmt->fetchAll();

$allEventsStmt = $pdo->prepare("
    SELECT e.*,
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) AS registered_count,
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND member_id = ?) AS is_registered,
           (SELECT status FROM event_registrations WHERE event_id = e.id AND member_id = ? LIMIT 1) AS reg_status
    FROM events e
    ORDER BY e.event_date ASC
");
$allEventsStmt->execute([$memberId, $memberId]);
$allEvents = $allEventsStmt->fetchAll();

foreach ($allEvents as &$ev) {
    $tStmt = $pdo->prepare("SELECT t.id, t.name FROM event_teams et JOIN teams t ON et.team_id = t.id WHERE et.event_id = ?");
    $tStmt->execute([$ev['id']]);
    $ev['teams'] = $tStmt->fetchAll();
}
unset($ev);

$annStmt = $pdo->query("SELECT a.*, m.full_name AS author_name FROM announcements a LEFT JOIN members m ON a.created_by = m.id ORDER BY a.created_at DESC LIMIT 5");
$announcements = $annStmt->fetchAll();

$myTeamsStmt = $pdo->prepare("SELECT t.name, t.description FROM team_members tm JOIN teams t ON tm.team_id = t.id WHERE tm.member_id = ? ORDER BY tm.joined_at DESC");
$myTeamsStmt->execute([$memberId]);
$myTeams = $myTeamsStmt->fetchAll();

$myMsgsStmt = $pdo->prepare("SELECT * FROM member_messages WHERE member_id = ? ORDER BY sent_at DESC LIMIT 20");
$myMsgsStmt->execute([$memberId]);
$myMessages = $myMsgsStmt->fetchAll();

$isAdmin = ($member['role'] === 'admin');

$validTabs = ['events', 'myevents', 'announcements', 'teams', 'messages', 'profile'];
$activeTab = $_GET['tab'] ?? 'events';
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'events';
}

setcookie('hack_theme', 'dark', time() + (365 * 24 * 3600), '/');
setcookie('hack_visited', 'true', time() + (365 * 24 * 3600), '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | HACK Club KUET</title>
    <meta name="description" content="HACK Club member dashboard — manage your events, send messages, and view your profile.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="myClub.css">
    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
    <link rel="stylesheet" href="auth.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .dash-tabs { display:flex; gap:4px; margin-bottom:24px; border-bottom:1px solid rgba(255,255,255,0.07); flex-wrap:wrap; }
        .dash-tab-btn { padding:10px 18px; border-radius:10px 10px 0 0; background:none; border:none; border-bottom:3px solid transparent; color:#64748b; font-size:0.82rem; font-weight:700; font-family:'Outfit',sans-serif; cursor:pointer; display:flex; align-items:center; gap:6px; transition:0.2s; text-transform:uppercase; letter-spacing:0.3px; text-decoration:none; }
        .dash-tab-btn:hover { color:#cbd5e1; }
        .dash-tab-btn.active { color:#00f3ff; border-bottom-color:#00f3ff; }
        .dash-tab-content { display:none; }
        .dash-tab-content.active { display:block; }

        .events-grid-dash { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:18px; }
        .ev-dash-card { background:rgba(10,15,25,0.65); border:1px solid rgba(0,243,255,0.12); border-radius:18px; padding:22px; transition:0.3s; display:flex; flex-direction:column; gap:10px; }
        .ev-dash-card:hover { border-color:rgba(0,243,255,0.35); transform:translateY(-2px); }
        .ev-dash-header { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; }
        .ev-dash-title { font-size:1rem; font-weight:800; color:#fff; flex:1; }
        .ev-date-pill { background:rgba(0,243,255,0.1); border:1px solid rgba(0,243,255,0.25); border-radius:10px; padding:5px 10px; font-size:0.68rem; color:#00f3ff; font-weight:800; text-align:center; flex-shrink:0; }
        .ev-date-pill strong { display:block; font-size:1.2rem; color:#fff; }
        .ev-meta { font-size:0.78rem; color:#64748b; display:flex; align-items:center; gap:5px; }
        .ev-desc { font-size:0.83rem; color:#64748b; line-height:1.5; }
        .ev-seats-bar { background:rgba(255,255,255,0.06); border-radius:10px; height:4px; overflow:hidden; }
        .ev-seats-fill { height:100%; border-radius:10px; background:linear-gradient(90deg,#00f3ff,#2d72fc); }
        .ev-seats-label { font-size:0.73rem; color:#475569; }
       
        .ev-teams-chips { display:flex; flex-wrap:wrap; gap:5px; }
        .ev-team-tag { background:rgba(45,114,252,0.1); border:1px solid rgba(45,114,252,0.25); border-radius:20px; padding:3px 10px; font-size:0.7rem; color:#7aa2f7; font-weight:700; }
        .ev-reg-btn { display:inline-flex; align-items:center; gap:6px; padding:9px 18px; border-radius:10px; font-size:0.82rem; font-weight:700; font-family:'Outfit',sans-serif; cursor:pointer; border:none; transition:0.25s; }
        .ev-reg-btn.register { background:linear-gradient(135deg,#00f3ff,#2d72fc); color:#fff; box-shadow:0 0 15px rgba(0,243,255,0.25); width:100%; justify-content:center; }
        .ev-reg-btn.register:hover { transform:translateY(-2px); box-shadow:0 0 25px rgba(0,243,255,0.4); }
        .ev-reg-btn.registered { background:rgba(0,229,160,0.1); border:1px solid rgba(0,229,160,0.3); color:#00e5a0; cursor:default; width:100%; justify-content:center; }
        .ev-reg-btn.full { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#475569; cursor:default; width:100%; justify-content:center; }

        .my-ev-list { display:flex; flex-direction:column; gap:12px; }
        .my-ev-item { display:flex; align-items:center; gap:14px; padding:14px 16px; background:rgba(5,8,16,0.5); border:1px solid rgba(0,243,255,0.1); border-radius:14px; transition:0.2s; }
        .my-ev-item:hover { border-color:rgba(0,243,255,0.3); }
        .my-ev-date { background:rgba(0,243,255,0.1); border:1px solid rgba(0,243,255,0.2); border-radius:10px; padding:6px 10px; text-align:center; min-width:50px; flex-shrink:0; }
        .my-ev-date span { display:block; font-size:0.65rem; font-weight:800; color:#00f3ff; text-transform:uppercase; }
        .my-ev-date strong { display:block; font-size:1.3rem; font-weight:900; color:#fff; line-height:1; }
        .my-ev-info { flex:1; }
        .my-ev-title { font-weight:700; color:#fff; font-size:0.92rem; margin-bottom:3px; }
        .my-ev-sub { font-size:0.73rem; color:#64748b; }
        .confirmed-badge { background:rgba(0,229,160,0.1); border:1px solid rgba(0,229,160,0.25); color:#00e5a0; border-radius:20px; padding:3px 10px; font-size:0.7rem; font-weight:700; white-space:nowrap; }

        .msg-compose-card { background:rgba(10,15,25,0.65); border:1px solid rgba(0,243,255,0.15); border-radius:20px; padding:28px; }
        .msg-compose-card h3 { font-size:1.1rem; font-weight:800; color:#fff; margin-bottom:20px; display:flex; align-items:center; gap:8px; }
        .compose-field { margin-bottom:16px; }
        .compose-field label { display:block; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#64748b; margin-bottom:8px; }
        .compose-field input, .compose-field textarea {
            width:100%; padding:12px 16px; background:rgba(5,8,16,0.8); border:1px solid rgba(0,243,255,0.2);
            border-radius:12px; color:#fff; font-family:'Outfit',sans-serif; font-size:0.9rem;
            transition:0.2s; outline:none; box-sizing:border-box;
        }
        .compose-field input:focus, .compose-field textarea:focus { border-color:#00f3ff; box-shadow:0 0 0 3px rgba(0,243,255,0.1); }
        .compose-field textarea { resize:vertical; min-height:120px; }
        .compose-send-btn { display:inline-flex; align-items:center; gap:8px; padding:12px 28px; background:linear-gradient(135deg,#00f3ff,#2d72fc); color:#fff; font-weight:700; border-radius:12px; border:none; cursor:pointer; font-family:'Outfit',sans-serif; font-size:0.92rem; transition:0.3s; box-shadow:0 0 20px rgba(0,243,255,0.3); }
        .compose-send-btn:hover { transform:translateY(-2px); box-shadow:0 0 30px rgba(0,243,255,0.5); }

        .sent-msgs { display:flex; flex-direction:column; gap:10px; margin-top:24px; }
        .sent-msg-item { padding:14px 16px; background:rgba(5,8,16,0.5); border:1px solid rgba(255,255,255,0.06); border-radius:12px; }
        .sent-msg-subject { font-weight:700; color:#cbd5e1; font-size:0.88rem; margin-bottom:4px; }
        .sent-msg-preview { font-size:0.8rem; color:#475569; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-bottom:6px; }
        .sent-msg-meta { font-size:0.72rem; color:#334155; }

        .ann-list { display:flex; flex-direction:column; gap:12px; }
        .ann-item { padding:18px 20px; background:rgba(0,243,255,0.03); border:1px solid rgba(0,243,255,0.12); border-left:4px solid #00f3ff; border-radius:12px; }
        .ann-item-title { font-weight:800; font-size:1rem; color:#fff; margin-bottom:6px; }
        .ann-item-meta { font-size:0.75rem; color:#475569; margin-bottom:8px; }
        .ann-item-body { color:#94a3b8; font-size:0.88rem; line-height:1.6; }

        .teams-dash-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:14px; }
        .team-dash-card { padding:18px; background:rgba(5,8,16,0.5); border:1px solid rgba(0,243,255,0.15); border-radius:14px; transition:0.2s; }
        .team-dash-card:hover { border-color:rgba(0,243,255,0.4); transform:translateY(-2px); }

        .profile-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .pi-row { padding:12px 16px; background:rgba(5,8,16,0.5); border:1px solid rgba(255,255,255,0.06); border-radius:12px; display:flex; justify-content:space-between; align-items:center; gap:10px; }
        .pi-label { font-size:0.75rem; text-transform:uppercase; letter-spacing:1px; color:#475569; font-weight:700; }
        .pi-val { font-size:0.88rem; color:#cbd5e1; font-weight:600; text-align:right; }

        .dash-toast { position:fixed; bottom:30px; right:30px; padding:14px 22px; border-radius:14px; font-size:0.9rem; font-weight:600; display:none; z-index:9999; max-width:340px; }
        .dash-toast.success { background:rgba(0,229,160,0.15); border:1px solid rgba(0,229,160,0.4); color:#00e5a0; }
        .dash-toast.error   { background:rgba(255,77,77,0.12);  border:1px solid rgba(255,77,77,0.4);  color:#ff8080; }

        @keyframes slideUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
        .ev-dash-card { animation:slideUp 0.3s ease both; }
    </style>
</head>
<body>
    <div class="bg-grid"></div>

    <nav class="navbar">
        <a href="index.php" class="logo">HACK<span class="dot">.</span></a>
        <ul>
            <li><a href="index.php">HOME</a></li>
            <?php if ($isAdmin): ?>
            <li><a href="admin.php">ADMIN</a></li>
            <?php endif; ?>
        </ul>
        <div class="nav-user-menu">
            <div class="nav-avatar" id="navAvatarBtn" onclick="toggleUserDropdown()">
                <span class="avatar-emoji"><?= htmlspecialchars($member['avatar']) ?></span>
                <span class="avatar-name"><?= htmlspecialchars(explode(' ', $member['full_name'])[0]) ?></span>
                <i class='bx bx-chevron-down'></i>
            </div>
            <div class="user-dropdown glass-card" id="userDropdown">
                <div class="dropdown-header">
                    <span class="d-avatar"><?= htmlspecialchars($member['avatar']) ?></span>
                    <div>
                        <div class="d-name"><?= htmlspecialchars($member['full_name']) ?></div>
                        <div class="d-role"><?= ucfirst($member['role']) ?> · <?= htmlspecialchars($member['department'] ?? 'KUET') ?></div>
                    </div>
                </div>
                <?php if ($isAdmin): ?>
                <a href="admin.php" class="dropdown-item"><i class='bx bx-cog'></i> Admin Panel</a>
                <?php endif; ?>
                <a href="logout.php" class="dropdown-item logout-item"><i class='bx bx-log-out'></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="dashboard-page">

        <div class="dash-header glass-panel">
            <div class="dash-welcome">
                <span class="dash-avatar"><?= htmlspecialchars($member['avatar']) ?></span>
                <div>
                    <h1>Welcome back, <span class="text-gradient"><?= htmlspecialchars(explode(' ', $member['full_name'])[0]) ?></span>!</h1>
                    <p>
                        <?= htmlspecialchars($member['student_id']) ?> &nbsp;·&nbsp;
                        <?= htmlspecialchars($member['department'] ?? '') ?> &nbsp;·&nbsp;
                        <?php
                        $yr = $member['year'];
                        $sfx = $yr == 1 ? 'st' : ($yr == 2 ? 'nd' : ($yr == 3 ? 'rd' : 'th'));
                        echo $yr ? $yr . $sfx . ' Year' : '';
                        ?>
                    </p>
                </div>
            </div>
            <div class="dash-stats">
                <div class="dash-stat-item">
                    <span class="ds-num"><?= count($myEvents) ?></span>
                    <span class="ds-label">Events Joined</span>
                </div>
                <div class="dash-stat-item">
                    <span class="ds-num"><?= count($myTeams) ?></span>
                    <span class="ds-label">Teams</span>
                </div>
                <div class="dash-stat-item">
                    <span class="ds-num"><?= count($myMessages) ?></span>
                    <span class="ds-label">Messages Sent</span>
                </div>
            </div>
        </div>

        <div class="dash-tabs" style="margin-top:28px;">
            <a href="dashboard.php?tab=events" class="dash-tab-btn <?= $activeTab === 'events' ? 'active' : '' ?>" id="dTabEventsBtn"><i class='bx bx-calendar'></i> Events</a>
            <a href="dashboard.php?tab=myevents" class="dash-tab-btn <?= $activeTab === 'myevents' ? 'active' : '' ?>" id="dTabMyEventsBtn"><i class='bx bx-calendar-check'></i> My Registrations</a>
            <a href="dashboard.php?tab=announcements" class="dash-tab-btn <?= $activeTab === 'announcements' ? 'active' : '' ?>" id="dTabAnnBtn"><i class='bx bx-megaphone'></i> Announcements</a>
            <a href="dashboard.php?tab=teams" class="dash-tab-btn <?= $activeTab === 'teams' ? 'active' : '' ?>" id="dTabTeamsBtn"><i class='bx bx-shield'></i> My Teams</a>
            <a href="dashboard.php?tab=messages" class="dash-tab-btn <?= $activeTab === 'messages' ? 'active' : '' ?>" id="dTabMsgBtn"><i class='bx bx-envelope'></i> Message Admin</a>
            <a href="dashboard.php?tab=profile" class="dash-tab-btn <?= $activeTab === 'profile' ? 'active' : '' ?>" id="dTabProfileBtn"><i class='bx bx-user'></i> Profile</a>
        </div>

        <div id="dTab-events" class="dash-tab-content <?= $activeTab === 'events' ? 'active' : '' ?>">
            <?php if (empty($allEvents)): ?>
            <div class="dash-section glass-card">
                <div class="empty-state"><i class='bx bx-calendar-x'></i><p>No events scheduled yet. Check back soon!</p></div>
            </div>
            <?php else: ?>
            <div class="events-grid-dash">
                <?php foreach ($allEvents as $i => $ev):
                    $pct = $ev['seats'] > 0 ? min(100, round(($ev['registered_count'] / $ev['seats']) * 100)) : 0;
                ?>
                <div class="ev-dash-card" style="animation-delay:<?= $i * 0.05 ?>s">
                    <div class="ev-dash-header">
                        <div class="ev-dash-title"><?= htmlspecialchars($ev['title']) ?></div>
                        <div class="ev-date-pill">
                            <?= htmlspecialchars($ev['event_month']) ?>
                            <strong><?= htmlspecialchars($ev['event_day']) ?></strong>
                        </div>
                    </div>
                    <div class="ev-meta"><i class='bx bx-map-pin'></i><?= htmlspecialchars($ev['location']) ?></div>
                    <div class="ev-meta"><i class='bx bx-time'></i><?= date('g:i A', strtotime($ev['start_time'])) ?> – <?= date('g:i A', strtotime($ev['end_time'])) ?></div>
                    <div class="ev-desc"><?= htmlspecialchars($ev['description']) ?></div>

                    <?php if (!empty($ev['teams'])): ?>
                    <div>
                        <div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#475569;margin-bottom:5px;">Participating Teams</div>
                        <div class="ev-teams-chips">
                            <?php foreach ($ev['teams'] as $t): ?>
                            <span class="ev-team-tag">🛡️ <?= htmlspecialchars($t['name']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div>
                        <div class="ev-seats-bar"><div class="ev-seats-fill" style="width:<?= $pct ?>%"></div></div>
                        <div class="ev-seats-label"><?= $ev['registered_count'] ?> / <?= $ev['seats'] ?> seats (<?= $pct ?>%)</div>
                    </div>

                    <div>
                        <?php if ($ev['is_registered']): ?>
                        <button class="ev-reg-btn registered" disabled>
                            <i class='bx bx-check-circle'></i>
                            <?= ($ev['reg_status'] ?? '') === 'joined' ? 'Joined via Team' : 'Registered' ?>
                        </button>
                        <?php elseif ($ev['registered_count'] >= $ev['seats']): ?>
                        <button class="ev-reg-btn full" disabled><i class='bx bx-block'></i> Full</button>
                        <?php else: ?>
                        <form method="POST" action="event_register.php">
                            <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
                            <button type="submit" class="ev-reg-btn register">
                                <i class='bx bx-plus-circle'></i> Join Event
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div id="dTab-myevents" class="dash-tab-content <?= $activeTab === 'myevents' ? 'active' : '' ?>">
            <?php if (empty($myEvents)): ?>
            <div class="dash-section glass-card">
                <div class="empty-state"><i class='bx bx-calendar-x'></i><p>You haven't joined any events yet. Browse the <strong>Events</strong> tab to register!</p></div>
            </div>
            <?php else: ?>
            <div class="dash-section glass-card">
                <h2 class="dash-section-title"><i class='bx bx-calendar-check'></i> Your Registered Events</h2>
                <div class="my-ev-list">
                    <?php foreach ($myEvents as $ev): ?>
                    <div class="my-ev-item">
                        <div class="my-ev-date">
                            <span><?= htmlspecialchars($ev['event_month']) ?></span>
                            <strong><?= htmlspecialchars($ev['event_day']) ?></strong>
                        </div>
                        <div class="my-ev-info">
                            <div class="my-ev-title"><?= htmlspecialchars($ev['title']) ?></div>
                            <div class="my-ev-sub"><i class='bx bx-map-pin'></i> <?= htmlspecialchars($ev['location']) ?> &nbsp;·&nbsp; Joined <?= date('M j, Y', strtotime($ev['registered_at'])) ?><?= ($ev['status'] ?? '') === 'joined' ? ' &nbsp;·&nbsp; <span style="color:#00f3ff;">via Team</span>' : '' ?></div>
                        </div>
                        <span class="confirmed-badge"><i class='bx bx-check'></i> <?= ($ev['status'] ?? '') === 'joined' ? 'Team Join' : 'Confirmed' ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div id="dTab-announcements" class="dash-tab-content <?= $activeTab === 'announcements' ? 'active' : '' ?>">
            <?php if (empty($announcements)): ?>
            <div class="dash-section glass-card">
                <div class="empty-state"><i class='bx bx-megaphone'></i><p>No announcements from the admin yet.</p></div>
            </div>
            <?php else: ?>
            <div class="dash-section glass-card">
                <h2 class="dash-section-title"><i class='bx bx-megaphone'></i> Club Announcements</h2>
                <div class="ann-list">
                    <?php foreach ($announcements as $ann): ?>
                    <div class="ann-item">
                        <div class="ann-item-title"><?= htmlspecialchars($ann['title']) ?></div>
                        <div class="ann-item-meta">
                            📢 <?= htmlspecialchars($ann['author_name'] ?? 'Admin') ?>
                            &nbsp;·&nbsp; <?= date('M j, Y', strtotime($ann['created_at'])) ?>
                        </div>
                        <div class="ann-item-body"><?= nl2br(htmlspecialchars($ann['content'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div id="dTab-teams" class="dash-tab-content <?= $activeTab === 'teams' ? 'active' : '' ?>">
            <?php if (empty($myTeams)): ?>
            <div class="dash-section glass-card">
                <div class="empty-state"><i class='bx bx-shield'></i><p>You are not part of any team yet. Teams are assigned by the admin.</p></div>
            </div>
            <?php else: ?>
            <div class="dash-section glass-card">
                <h2 class="dash-section-title"><i class='bx bx-shield'></i> My Teams</h2>
                <div class="teams-dash-grid">
                    <?php foreach ($myTeams as $t): ?>
                    <div class="team-dash-card">
                        <div style="font-size:1.5rem;margin-bottom:10px;">🛡️</div>
                        <div style="font-weight:800;font-size:0.95rem;color:#fff;margin-bottom:6px;"><?= htmlspecialchars($t['name']) ?></div>
                        <div style="font-size:0.8rem;color:#64748b;line-height:1.4;"><?= htmlspecialchars($t['description'] ?: 'No description.') ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div id="dTab-messages" class="dash-tab-content <?= $activeTab === 'messages' ? 'active' : '' ?>">
            <div class="dash-grid">
                <div class="msg-compose-card">
                    <h3><i class='bx bx-edit' style="color:#00f3ff;"></i> Contact Admin</h3>
                    <form id="msgForm" method="POST" action="send_message.php">
                        <div class="compose-field">
                            <label>Subject *</label>
                            <input type="text" name="subject" placeholder="What's on your mind?" required maxlength="200">
                        </div>
                        <div class="compose-field">
                            <label>Message *</label>
                            <textarea name="message" placeholder="Write your message to the club admin…" required maxlength="2000"></textarea>
                        </div>
                        <button type="submit" class="compose-send-btn" id="msgSendBtn">
                            <i class='bx bx-send'></i> Send Message
                        </button>
                    </form>
                </div>

                <div class="msg-compose-card">
                    <h3><i class='bx bx-inbox' style="color:#00f3ff;"></i> My Sent Messages</h3>
                    <?php if (empty($myMessages)): ?>
                    <div class="empty-state" style="padding:30px 0;"><i class='bx bx-message-square-x'></i><p style="margin:0;">No messages sent yet.</p></div>
                    <?php else: ?>
                    <div class="sent-msgs" id="sentMsgList">
                        <?php foreach ($myMessages as $msg): ?>
                        <div class="sent-msg-item">
                            <div class="sent-msg-subject"><?= htmlspecialchars($msg['subject']) ?></div>
                            <div class="sent-msg-preview"><?= htmlspecialchars($msg['message']) ?></div>
                            <div class="sent-msg-meta"><?= date('M j, Y H:i', strtotime($msg['sent_at'])) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="dTab-profile" class="dash-tab-content <?= $activeTab === 'profile' ? 'active' : '' ?>">
            <div class="dash-section glass-card">
                <h2 class="dash-section-title"><i class='bx bx-user-circle'></i> My Profile</h2>
                <div style="display:flex;align-items:center;gap:20px;margin-bottom:28px;padding:20px;background:rgba(0,243,255,0.03);border:1px solid rgba(0,243,255,0.1);border-radius:16px;">
                    <span style="font-size:3rem;"><?= htmlspecialchars($member['avatar']) ?></span>
                    <div>
                        <div style="font-size:1.5rem;font-weight:900;color:#fff;"><?= htmlspecialchars($member['full_name']) ?></div>
                        <div style="color:#64748b;font-size:0.88rem;margin-top:4px;"><?= htmlspecialchars($member['email']) ?></div>
                        <div style="margin-top:8px;">
                            <span style="background:<?= $isAdmin ? 'rgba(0,243,255,0.1)' : 'rgba(255,255,255,0.06)' ?>;color:<?= $isAdmin ? '#00f3ff' : '#64748b' ?>;border:1px solid <?= $isAdmin ? 'rgba(0,243,255,0.3)' : 'rgba(255,255,255,0.1)' ?>;border-radius:20px;padding:3px 14px;font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">
                                <?= $isAdmin ? '⚙️ Administrator' : '👤 Member' ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="profile-grid">
                    <div class="pi-row"><span class="pi-label">Student ID</span><span class="pi-val"><?= htmlspecialchars($member['student_id']) ?></span></div>
                    <div class="pi-row"><span class="pi-label">Department</span><span class="pi-val"><?= htmlspecialchars($member['department'] ?? '—') ?></span></div>
                    <div class="pi-row"><span class="pi-label">Year</span><span class="pi-val"><?= $member['year'] ? $member['year'] . ($member['year']==1?'st':($member['year']==2?'nd':($member['year']==3?'rd':'th'))) . ' Year' : '—' ?></span></div>
                    <div class="pi-row"><span class="pi-label">Role</span><span class="pi-val"><?= ucfirst($member['role']) ?></span></div>
                    <div class="pi-row"><span class="pi-label">Member Since</span><span class="pi-val"><?= date('F Y', strtotime($member['created_at'])) ?></span></div>
                    <div class="pi-row"><span class="pi-label">Last Login</span><span class="pi-val"><?= $member['last_login'] ? date('M j, Y H:i', strtotime($member['last_login'])) : 'First login' ?></span></div>
                </div>
            </div>
        </div>

    </div>

    <?php
    if (isset($_SESSION['status_message'])):
    ?>
    <div class="dash-toast <?= htmlspecialchars($_SESSION['status_type'] ?? 'success') ?>" id="dashGlobalToast" style="display:block;">
        <?= htmlspecialchars($_SESSION['status_message']) ?>
    </div>
    <?php
        unset($_SESSION['status_message']);
        unset($_SESSION['status_type']);
    endif;
    ?>

    <script>
    function toggleUserDropdown() { document.getElementById('userDropdown').classList.toggle('show'); }
    document.addEventListener('click', function(e) {
        const btn = document.getElementById('navAvatarBtn');
        const dd  = document.getElementById('userDropdown');
        if (btn && dd && !btn.contains(e.target)) dd.classList.remove('show');
    });

    document.addEventListener('DOMContentLoaded', function() {
        const toast = document.getElementById('dashGlobalToast');
        if (toast) {
            setTimeout(function() {
                toast.style.transition = 'opacity 0.6s ease';
                toast.style.opacity = '0';
                setTimeout(function() { toast.style.display = 'none'; }, 600);
            }, 3500);
        }
    });
    </script>
</body>
</html>