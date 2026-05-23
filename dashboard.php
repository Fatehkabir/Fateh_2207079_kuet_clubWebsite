<?php
require_once 'auth.php';
require_once 'db.php';
requireLogin();

$memberId = $_SESSION['member_id'];

$stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$memberId]);
$member = $stmt->fetch();

if (!$member) {
    header('Location: logout.php');
    exit();
}

$msgStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM contact_messages WHERE email = ?");
$msgStmt->execute([$member['email']]);
$msgCount = $msgStmt->fetch()['total'];

$regStmt = $pdo->prepare("
    SELECT e.title, e.event_month, e.event_day, er.registered_at
    FROM event_registrations er
    JOIN events e ON er.event_id = e.id
    WHERE er.member_id = ?
    ORDER BY er.registered_at DESC
");
$regStmt->execute([$memberId]);
$myEvents = $regStmt->fetchAll();

$allEventsStmt = $pdo->prepare("
    SELECT e.*,
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) AS registered_count,
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND member_id = ?) AS is_registered
    FROM events e
    ORDER BY e.event_date ASC
");
$allEventsStmt->execute([$memberId]);
$allEvents = $allEventsStmt->fetchAll();

$theme = $_COOKIE['hack_theme'] ?? 'dark';
$isAdmin = ($member['role'] === 'admin');


$annStmt = $pdo->query("SELECT a.*, m.full_name AS author_name FROM announcements a LEFT JOIN members m ON a.created_by = m.id ORDER BY a.created_at DESC LIMIT 5");
$announcements = $annStmt->fetchAll();


$myTeamsStmt = $pdo->prepare("SELECT t.name, t.description FROM team_members tm JOIN teams t ON tm.team_id = t.id WHERE tm.member_id = ? ORDER BY tm.joined_at DESC");
$myTeamsStmt->execute([$memberId]);
$myTeams = $myTeamsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | HACK Club KUET</title>
    <meta name="description" content="HACK Club member dashboard — manage your events and profile.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="copyMyClub.css">
    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
    <link rel="stylesheet" href="auth.css">
    <link rel="stylesheet" href="dashboard.css">
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
                        <?= $member['year'] ? $member['year'] . (in_array($member['year'], [1]) ? 'st' : ($member['year'] == 2 ? 'nd' : ($member['year'] == 3 ? 'rd' : 'th'))) . ' Year' : '' ?>
                    </p>
                </div>
            </div>
            <div class="dash-stats">
                <div class="dash-stat-item">
                    <span class="ds-num"><?= count($myEvents) ?></span>
                    <span class="ds-label">Events Joined</span>
                </div>
                <div class="dash-stat-item">
                    <span class="ds-num"><?= $msgCount ?></span>
                    <span class="ds-label">Messages Sent</span>
                </div>
                <div class="dash-stat-item">
                    <span class="ds-num"><?= $member['year'] ?? '—' ?></span>
                    <span class="ds-label">Year</span>
                </div>
            </div>
        </div>

        <div class="dash-grid">
            <div class="dash-col">
                <div class="dash-section glass-card">
                    <h2 class="dash-section-title"><i class='bx bx-calendar-event'></i> My Registered Events</h2>
                    <?php if (empty($myEvents)): ?>
                        <div class="empty-state">
                            <i class='bx bx-calendar-x'></i>
                            <p>You haven't registered for any events yet.</p>
                        </div>
                    <?php else: ?>
                        <ul class="my-events-list">
                            <?php foreach ($myEvents as $ev): ?>
                            <li class="my-event-item">
                                <div class="mev-date">
                                    <span><?= htmlspecialchars($ev['event_month']) ?></span>
                                    <strong><?= htmlspecialchars($ev['event_day']) ?></strong>
                                </div>
                                <div class="mev-info">
                                    <div class="mev-title"><?= htmlspecialchars($ev['title']) ?></div>
                                    <div class="mev-reg">Registered <?= date('M j, Y', strtotime($ev['registered_at'])) ?></div>
                                </div>
                                <span class="mev-badge">Confirmed</span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="dash-section glass-card">
                    <h2 class="dash-section-title"><i class='bx bx-user-circle'></i> Profile Info</h2>
                    <div class="profile-info-list">
                        <div class="pi-item"><span class="pi-label">Email</span><span class="pi-val"><?= htmlspecialchars($member['email']) ?></span></div>
                        <div class="pi-item"><span class="pi-label">Student ID</span><span class="pi-val"><?= htmlspecialchars($member['student_id']) ?></span></div>
                        <div class="pi-item"><span class="pi-label">Department</span><span class="pi-val"><?= htmlspecialchars($member['department'] ?? '—') ?></span></div>
                        <div class="pi-item"><span class="pi-label">Member Since</span><span class="pi-val"><?= date('F Y', strtotime($member['created_at'])) ?></span></div>
                        <div class="pi-item"><span class="pi-label">Last Login</span><span class="pi-val"><?= $member['last_login'] ? date('M j, Y H:i', strtotime($member['last_login'])) : 'First login' ?></span></div>
                    </div>
                </div>
            </div>

            <div class="dash-col">
                <div class="dash-section glass-card">
                    <h2 class="dash-section-title"><i class='bx bx-calendar-plus'></i> Upcoming Events</h2>
                    <div id="toast" class="toast" style="display:none;"></div>
                    <div class="events-register-list">
                        <?php foreach ($allEvents as $ev): ?>
                        <div class="er-card">
                            <div class="er-date-badge">
                                <span><?= htmlspecialchars($ev['event_month']) ?></span>
                                <strong><?= htmlspecialchars($ev['event_day']) ?></strong>
                            </div>
                            <div class="er-info">
                                <div class="er-title"><?= htmlspecialchars($ev['title']) ?></div>
                                <div class="er-desc"><?= htmlspecialchars($ev['description']) ?></div>
                                <div class="er-meta">
                                    <span><i class='bx bx-group'></i> <?= $ev['registered_count'] ?>/<?= $ev['seats'] ?> seats</span>
                                </div>
                            </div>
                            <div class="er-action">
                                <?php if ($ev['is_registered']): ?>
                                    <button class="er-btn er-btn-registered" disabled>
                                        <i class='bx bx-check'></i> Registered
                                    </button>
                                <?php elseif ($ev['registered_count'] >= $ev['seats']): ?>
                                    <button class="er-btn er-btn-full" disabled>Full</button>
                                <?php else: ?>
                                    <button class="er-btn er-btn-register"
                                            onclick="registerEvent(<?= $ev['id'] ?>, this)"
                                            data-event-id="<?= $ev['id'] ?>">
                                        <i class='bx bx-plus'></i> Register
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($announcements)): ?>
        <div class="dash-section glass-card" style="margin-top:30px;">
            <h2 class="dash-section-title"><i class='bx bx-megaphone'></i> Club Announcements</h2>
            <div style="display:flex;flex-direction:column;gap:14px;">
                <?php foreach ($announcements as $ann): ?>
                <div style="
                    padding: 18px 20px;
                    background: rgba(0,243,255,0.04);
                    border: 1px solid rgba(0,243,255,0.15);
                    border-left: 4px solid #00f3ff;
                    border-radius: 12px;
                ">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;">
                        <div style="font-weight:800;font-size:1rem;color:#fff;"><?= htmlspecialchars($ann['title']) ?></div>
                        <div style="font-size:0.75rem;color:#475569;white-space:nowrap;">
                            <?= date('M j, Y', strtotime($ann['created_at'])) ?>
                            &nbsp;·&nbsp; <?= htmlspecialchars($ann['author_name'] ?? 'Admin') ?>
                        </div>
                    </div>
                    <div style="color:#94a3b8;font-size:0.88rem;line-height:1.6;margin-top:8px;"><?= nl2br(htmlspecialchars($ann['content'])) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="dash-section glass-card" style="margin-top:30px;">
            <h2 class="dash-section-title"><i class='bx bx-shield'></i> My Teams</h2>
            <?php if (empty($myTeams)): ?>
                <div class="empty-state">
                    <i class='bx bx-group'></i>
                    <p>You are not assigned to any team yet. Teams are managed by the admin.</p>
                </div>
            <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;">
                <?php foreach ($myTeams as $t): ?>
                <div style="
                    padding:18px;
                    background:rgba(5,8,16,0.5);
                    border:1px solid rgba(0,243,255,0.15);
                    border-radius:14px;
                    transition:0.2s;
                " onmouseover="this.style.borderColor='rgba(0,243,255,0.4)'" onmouseout="this.style.borderColor='rgba(0,243,255,0.15)'">
                    <div style="font-size:1.6rem;margin-bottom:8px;">🛡️</div>
                    <div style="font-weight:800;color:#fff;font-size:0.95rem;margin-bottom:5px;"><?= htmlspecialchars($t['name']) ?></div>
                    <div style="font-size:0.8rem;color:#64748b;line-height:1.4;"><?= htmlspecialchars($t['description'] ?: 'No description.') ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <script>
    function toggleUserDropdown() {
        const dd = document.getElementById('userDropdown');
        dd.classList.toggle('show');
    }
    document.addEventListener('click', function(e) {
        const btn = document.getElementById('navAvatarBtn');
        const dd  = document.getElementById('userDropdown');
        if (!btn.contains(e.target)) dd.classList.remove('show');
    });

    function showToast(msg, type) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.className = 'toast toast-' + type;
        t.style.display = 'block';
        setTimeout(() => { t.style.display = 'none'; }, 3500);
    }

    function registerEvent(eventId, btn) {
        btn.disabled = true;
        btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Registering...";
        fetch('event_register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'event_id=' + eventId
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                btn.innerHTML = "<i class='bx bx-check'></i> Registered";
                btn.className = 'er-btn er-btn-registered';
                showToast('Successfully registered for the event!', 'success');
                const meta = btn.closest('.er-card').querySelector('.er-meta span');
                if (meta) {
                    const parts = meta.textContent.trim().split('/');
                    if (parts.length === 2) {
                        const newCount = parseInt(parts[0].replace(/\D/g, '')) + 1;
                        meta.innerHTML = "<i class='bx bx-group'></i> " + newCount + "/" + parts[1].trim();
                    }
                }
            } else {
                btn.disabled = false;
                btn.innerHTML = "<i class='bx bx-plus'></i> Register";
                showToast(data.message || 'Registration failed.', 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = "<i class='bx bx-plus'></i> Register";
            showToast('Network error. Please try again.', 'error');
        });
    }
    </script>
</body>
</html>
