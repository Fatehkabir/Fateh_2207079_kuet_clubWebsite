<?php
require_once 'auth.php';
require_once 'db.php';

$isLoggedIn = isLoggedIn();
$isAdmin = isAdmin();
$avatar = '';
$fullName = '';
$firstName = '';

if ($isLoggedIn) {

    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([getUserId()]);
    $member = $stmt->fetch();
    if ($member) {
        $avatar = $member['avatar'];
        $fullName = $member['full_name'];
        $firstName = explode(' ', $fullName)[0];
    } else {

        header('Location: logout.php');
        exit();
    }
}

$eventsStmt = $pdo->query("
    SELECT * FROM events
    WHERE event_date >= CURDATE()
    ORDER BY event_date ASC
    LIMIT 3
");
$eventsList = $eventsStmt->fetchAll();

if (count($eventsList) < 3) {
    $existingIds = array_column($eventsList, 'id');
    $placeholders = $existingIds ? implode(',', array_fill(0, count($existingIds), '?')) : '';
    $sql = "
        SELECT * FROM events
        " . ($existingIds ? "WHERE id NOT IN ($placeholders)" : "") . "
        ORDER BY updated_at DESC, event_date DESC
        LIMIT " . (3 - count($eventsList));
    $fillStmt = $pdo->prepare($sql);
    $fillStmt->execute($existingIds);
    $eventsList = array_merge($eventsList, $fillStmt->fetchAll());
}

foreach ($eventsList as &$ev) {
    $ev['teams'] = [];
    try {
        $tStmt = $pdo->prepare("SELECT t.id, t.name FROM event_teams et JOIN teams t ON et.team_id = t.id WHERE et.event_id = ?");
        $tStmt->execute([$ev['id']]);
        $ev['teams'] = $tStmt->fetchAll();
    } catch (PDOException $e) {
     
    }
}
unset($ev);

$teamsList = $pdo->query("SELECT * FROM teams ORDER BY created_at DESC")->fetchAll();
foreach ($teamsList as &$team) {
    $mStmt = $pdo->prepare("
        SELECT m.full_name, m.avatar, m.department, m.role
        FROM team_members tm
        JOIN members m ON tm.member_id = m.id
        WHERE tm.team_id = ?
        ORDER BY tm.joined_at ASC
    ");
    $mStmt->execute([$team['id']]);
    $team['members'] = $mStmt->fetchAll();
}
unset($team);


$statMembers = $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
$statEvents  = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$statRegs    = $pdo->query("SELECT COUNT(*) FROM event_registrations")->fetchColumn();
$statTeams   = $pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();


?>

<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HACK | KUET Hardware Club</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="myClub.css">
    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
    <style>
        .dropdown-item:hover {
            background: rgba(0, 243, 255, 0.08);
            color: #00f3ff !important;
        }
    </style>
</head>
<body>

    <div class="bg-glow"></div>
    <div class="bg-grid"></div>

    <div id="welcomeBanner" class="welcome-banner" style="display:none;">
        <i class='bx bx-user-check'></i>
        <span id="welcomeBannerText">Welcome back!</span>
        <button onclick="dismissBanner()" class="banner-close"><i class='bx bx-x'></i></button>
    </div>
    <nav class="navbar">
        <a href="#" class="logo">HACK<span class="dot">.</span></a>
        <ul>
            <li class="active"><a href="#">HOME</a></li>
            <li><a href="#about">ABOUT</a></li>
            <li><a href="#activities">ACTIVITIES</a></li>
            <li><a href="#events">EVENTS</a></li>
            <li><a href="#team">TEAM</a></li>
            <li><a href="#contact">CONTACT</a></li>
            <?php if ($isLoggedIn): ?>
            <li><a href="dashboard.php">DASHBOARD</a></li>
            <?php endif; ?>
        </ul>
        <?php if ($isLoggedIn): ?>
        <div class="nav-user-menu" style="position: relative;">
            <div class="nav-avatar" id="navAvatarBtn" onclick="toggleUserDropdown()" style="cursor: pointer; display: flex; align-items: center; gap: 8px; color: #fff; background: rgba(255,255,255,0.06); padding: 6px 12px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1);">
                <span class="avatar-emoji"><?= htmlspecialchars($avatar) ?></span>
                <span class="avatar-name" style="font-weight: 600; font-size: 0.9rem;"><?= htmlspecialchars($firstName) ?></span>
                <i class='bx bx-chevron-down'></i>
            </div>
            <div class="user-dropdown glass-card" id="userDropdown" style="position: absolute; right: 0; top: 50px; background: rgba(10,15,25,0.95); border: 1px solid rgba(0,243,255,0.15); border-radius: 12px; padding: 15px; display: none; flex-direction: column; gap: 8px; z-index: 1000; box-shadow: 0 10px 30px rgba(0,0,0,0.5); min-width: 180px;">
                <div class="dropdown-header" style="display: flex; align-items: center; gap: 10px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 10px; margin-bottom: 5px;">
                    <span class="d-avatar" style="font-size: 1.5rem;"><?= htmlspecialchars($avatar) ?></span>
                    <div>
                        <div class="d-name" style="font-weight: 700; color: #fff; font-size: 0.9rem; max-width: 110px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($fullName) ?></div>
                        <div class="d-role" style="font-size: 0.72rem; color: #64748b;"><?= $isAdmin ? 'Administrator' : 'Member' ?></div>
                    </div>
                </div>
                <a href="dashboard.php" class="dropdown-item" style="color: #cbd5e1; text-decoration: none; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; padding: 8px; border-radius: 6px; transition: 0.2s;"><i class='bx bx-grid-alt'></i> Dashboard</a>
                <?php if ($isAdmin): ?>
                <a href="admin.php" class="dropdown-item" style="color: #cbd5e1; text-decoration: none; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; padding: 8px; border-radius: 6px; transition: 0.2s;"><i class='bx bx-cog'></i> Admin Panel</a>
                <?php endif; ?>
                <a href="logout.php" class="dropdown-item logout-item" style="color: #ff6b6b; text-decoration: none; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; padding: 8px; border-radius: 6px; transition: 0.2s; border-top: 1px solid rgba(255,255,255,0.05); margin-top: 5px;"><i class='bx bx-log-out'></i> Logout</a>
            </div>
        </div>
        <?php else: ?>
        <a href="login.php" class="nav-login-btn" id="navLoginBtn">LOGIN</a>
        <?php endif; ?>
    </nav>
    <header class="home" id="home">
        <div class="home-info">
            <div class="badge">Innovation Lab</div>
            <h1>Hardware <br> Engineering <br> <span class="text-gradient">Unleashed</span></h1>
            <h2>Embrace your hardware skills with us.</h2>
            <p>HACK is the premier hardware and robotics club of KUET. We are here to push the boundaries of embedded systems, automation, and creative technology.</p>
            <div class="sci">
                <a href="https://www.facebook.com/groups/172448752788508" class="facebook"><i class="bx bxl-facebook"></i></a>
                <a href="#" class="twitter"><i class="bx bxl-linkedin"></i></a>
                <a href="#" class="instagram"><i class="bx bxl-instagram"></i></a>
                <a href="#" class="github"><i class="bx bxl-github"></i></a>
            </div>
        </div>
        <div class="home-img">
            <div class="img-box-wrapper">
                <div class="img-box">
                    <div class="img-item">
                        <img src="hack.png" alt="Hack Club Logo">
                    </div>
                </div>
            </div>
        </div>
    </header>

    <section class="stats-section">
        <div class="stats-bar glass-panel">
            <div class="stat-item">
                <span class="stat-num"><?= $statMembers ?>+</span>
                <div class="stat-label">Registered Members</div>
            </div>
            <div class="stat-item">
                <span class="stat-num"><?= $statEvents ?>+</span>
                <div class="stat-label">Events Hosted</div>
            </div>
            <div class="stat-item">
                <span class="stat-num"><?= $statRegs ?>+</span>
                <div class="stat-label">Registrations</div>
            </div>
            <div class="stat-item">
                <span class="stat-num"><?= $statTeams ?>+</span>
                <div class="stat-label">Active Teams</div>
            </div>
            <div class="stat-item">
                <span class="stat-num">10+</span>
                <div class="stat-label">Years Active</div>
            </div>
        </div>
    </section>

    <section class="about" id="about">
        <div class="section-header">
            <div class="section-subtitle">About Us</div>
            <h2>Where engineers <span class="text-gradient">build real things</span></h2>
            <p class="section-desc">HACK — Hardware, Automation & Creative Knowledge — is KUET's dedicated club for students who love getting their hands dirty. We believe the best engineering is built, not just studied.</p>
        </div>
        <div class="about-pillars">
            <div class="pillar glass-card">
                <div class="pillar-icon">⚡</div>
                <div class="pillar-title">Build</div>
                <div class="pillar-text">Hands-on robotics, PCB design, and embedded system projects every semester.</div>
            </div>
            <div class="pillar glass-card">
                <div class="pillar-icon">🧠</div>
                <div class="pillar-title">Learn</div>
                <div class="pillar-text">Workshops on Arduino, ESP32, Raspberry Pi, and more — for all skill levels.</div>
            </div>
            <div class="pillar glass-card">
                <div class="pillar-icon">🏆</div>
                <div class="pillar-title">Compete</div>
                <div class="pillar-text">Represent KUET in national robotics and hardware competitions across Bangladesh.</div>
            </div>
            <div class="pillar glass-card">
                <div class="pillar-icon">🤝</div>
                <div class="pillar-title">Connect</div>
                <div class="pillar-text">Network with alumni, industry professionals, and fellow makers in our community.</div>
            </div>
        </div>
    </section>

     <section id="activities" class="activities">
        <div class="section-header">
            <div class="section-subtitle">What We Do</div>
            <h2 class="section-title">Core <span class="text-gradient">Activities</span></h2>
            <p class="section-desc">Six focus areas that shape every HACK member into a well-rounded hardware engineer.</p>
        </div>

        <div class="act-grid">
            <div class="act-card glass-card">
                <span class="act-icon">🤖</span>
                <div class="act-title">Robotics</div>
                <p class="act-desc">Design, build, and program autonomous robots for competitions and research — from line-followers to soccer bots.</p>
            </div>
            <div class="act-card glass-card">
                <span class="act-icon">🔌</span>
                <div class="act-title">Embedded Systems</div>
                <p class="act-desc">Microcontroller programming with Arduino, STM32, and PIC — from basic I/O to complex real-time applications.</p>
            </div>
            <div class="act-card glass-card">
                <span class="act-icon">📡</span>
                <div class="act-title">IoT & Wireless</div>
                <p class="act-desc">Build connected devices using ESP8266, ESP32, and LoRa for smart home, agriculture, and industrial automation.</p>
            </div>
            <div class="act-card glass-card">
                <span class="act-icon">🛠️</span>
                <div class="act-title">PCB Design</div>
                <p class="act-desc">Learn KiCad and EagleCAD, etch your own boards, and manufacture custom PCBs for real-world deployment.</p>
            </div>
            <div class="act-card glass-card">
                <span class="act-icon">⚙️</span>
                <div class="act-title">Power Electronics</div>
                <p class="act-desc">Motor drivers, inverters, and switching regulators — practical power electronics for engineering challenges.</p>
            </div>
            <div class="act-card glass-card">
                <span class="act-icon">🖨️</span>
                <div class="act-title">3D Printing & Fab</div>
                <p class="act-desc">Prototype enclosures and mechanical parts using the club's 3D printers and fabrication tools.</p>
            </div>
        </div>
    </section>

        <section class="events" id="events">
        <div class="section-header">
            <div class="section-subtitle">What's Next</div>
            <h2 class="section-title">Upcoming <span class="text-gradient">Events</span></h2>
        </div>
        <div class="events-grid">
            <?php if (empty($eventsList)): ?>
                <p style="color:#64748b; text-align:center; grid-column: 1/-1; padding: 40px 0; font-size: 1.1rem;">No upcoming events scheduled at the moment. Check back later!</p>
            <?php else: ?>
                <?php foreach ($eventsList as $ev): ?>
                <div class="event-card glass-card" style="display: flex; flex-direction: column; justify-content: space-between; align-items: stretch; min-height: 280px; padding: 25px;">
                    <div style="display: flex; gap: 15px; align-items: flex-start; margin-bottom: 15px;">
                        <div class="event-date" style="flex-shrink: 0;">
                            <span class="month"><?= htmlspecialchars($ev['event_month']) ?></span>
                            <span class="day"><?= htmlspecialchars($ev['event_day']) ?></span>
                        </div>
                        <div class="event-info" style="flex: 1;">
                            <h3 style="font-size: 1.15rem; font-weight: 800; color: #fff; margin-bottom: 6px;"><?= htmlspecialchars($ev['title']) ?></h3>
                            <p style="color: #64748b; font-size: 0.85rem; line-height: 1.5; margin-bottom: 10px;"><?= htmlspecialchars($ev['description']) ?></p>
                        </div>
                    </div>
                    <?php if (!empty($ev['teams'])): ?>
                    <div style="margin-top: auto; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.06); margin-bottom: 12px;">
                        <div style="font-size: 0.68rem; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Participating Teams</div>
                        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                            <?php foreach ($ev['teams'] as $t): ?>
                            <span style="background: rgba(0, 243, 255, 0.08); border: 1px solid rgba(0, 243, 255, 0.2); border-radius: 20px; padding: 3px 10px; font-size: 0.7rem; color: #00f3ff; font-weight: 700;">🛡️ <?= htmlspecialchars($t['name']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
                        <span style="font-size: 0.75rem; color: #475569;"><i class='bx bx-map-pin' style="color: #00f3ff;"></i> <?= htmlspecialchars($ev['location']) ?></span>
                        <a href="dashboard.php" class="event-btn" style="position: static; transform: none; display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #00f3ff, #2d72fc); color: white; text-decoration: none; box-shadow: 0 0 10px rgba(0,243,255,0.3);"><i class='bx bx-right-arrow-alt'></i></a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>


        <section class="team" id="team">
        <div class="section-header">
            <div class="section-subtitle">Meet The Makers</div>
            <h2 class="section-title">Our <span class="text-gradient">Team</span></h2>
        </div>
        
        <div class="team-teams-container">
            <?php if (empty($teamsList)): ?>
                <p style="color:#64748b; text-align:center; padding: 40px 0; font-size: 1.05rem;">No teams have been created yet. Check back soon!</p>
            <?php else: ?>
                <?php foreach ($teamsList as $team): ?>
                    <div class="team-division" style="margin-bottom: 40px;">
                        <h3 class="team-division-title" style="color: #00f3ff; font-size: 1.25rem; font-weight: 800; margin-bottom: 8px; text-align: center; text-transform: uppercase; letter-spacing: 1px;">🛡️ <?= htmlspecialchars($team['name']) ?></h3>
                        <p class="team-division-desc" style="color: #ffffff; font-size: 0.85rem; text-align: center; margin-bottom: 24px; max-width: 600px; margin-left: auto; margin-right: auto;"><?= htmlspecialchars($team['description'] ?: 'Active HACK division.') ?></p>
                        
                        <div class="team-grid" style="display: flex; flex-wrap: wrap; justify-content: center; gap: 20px;">
                            <?php if (empty($team['members'])): ?>
                                <p style="color: #475569; font-size: 0.85rem; text-align: center;">No members in this division yet.</p>
                            <?php else: ?>
                                <?php foreach ($team['members'] as $tm): ?>
                                    <div class="team-member glass-card" style="flex: 1 1 200px; max-width: 250px; text-align: center; padding: 25px 20px;">
                                        <div class="member-img-placeholder glow-ring" style="font-size: 2.2rem; display: flex; align-items: center; justify-content: center; width: 60px; height: 60px; margin: 0 auto 15px; border-radius: 50%; background: rgba(0,243,255,0.06); border: 2px solid rgba(0,243,255,0.3); box-shadow: 0 0 15px rgba(0,243,255,0.2);"><?= htmlspecialchars($tm['avatar'] ?: '👤') ?></div>
                                        <h3 style="font-size: 1.05rem; font-weight: 700; color: #fff; margin-bottom: 4px;"><?= htmlspecialchars($tm['full_name']) ?></h3>
                                        <p class="role" style="color: #00f3ff; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 4px;"><?= htmlspecialchars($tm['department'] ?: 'KUET') ?></p>
                                        <p style="color: #64748b; font-size: 0.78rem;"><?= ucfirst(htmlspecialchars($tm['role'])) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>


        <section class="contact" id="contact">
        <div class="section-header">
            <div class="section-subtitle">Get In Touch</div>
            <h2 class="section-title">Contact <span class="text-gradient">Us</span></h2>
        </div>
        <div class="contact-container">
            <div class="contact-info glass-card">
                <h3>Join the community!</h3>
                <p>Have an idea for a project? Want to collaborate? Or just want to say hi? Drop us a message.</p>
                <ul>
                    <li>
                        <div class="icon-box"><i class='bx bx-map'></i></div>
                        <span>ECE Building, KUET, Khulna</span>
                    </li>
                    <li>
                        <div class="icon-box"><i class='bx bx-envelope' ></i></div>
                        <span>contact@hack.kuet.ac.bd</span>
                    </li>
                    <li>
                        <div class="icon-box"><i class='bx bx-phone' ></i></div>
                        <span>+880 1234-567890</span>
                    </li>
                </ul>
            </div>
            <div class="contact-form-container glass-card">
                <form action="contact.php" method="POST" class="contact-form">
                    <div class="input-box">
                        <input type="text" name="name" placeholder="Your Name" required>
                    </div>
                    <div class="input-box">
                        <input type="email" name="email" placeholder="Your Email" required>
                    </div>
                    <div class="input-box">
                        <textarea name="message" cols="30" rows="5" placeholder="Your Message" required></textarea>
                    </div>
                    <button type="submit" class="btn-submit">
                        <span>Send Message</span>
                        <i class='bx bx-send'></i>
                    </button>
                </form>
            </div>
        </div>
    </section>

        <footer class="footer">
        <div class="footer-text">
            <p>Copyright &copy; 2024 by HACK Club | All Rights Reserved.</p>
        </div>
        <div class="footer-iconTop">
            <a href="#home"><i class='bx bx-up-arrow-alt'></i></a>
        </div>
    </footer>

    <script src="myClub.js"></script>
    <script>
    function toggleUserDropdown() {
        const dd = document.getElementById('userDropdown');
        if (dd) {
            dd.style.display = dd.style.display === 'flex' ? 'none' : 'flex';
        }
    }
    
    document.addEventListener('click', function(e) {
        const btn = document.getElementById('navAvatarBtn');
        const dd  = document.getElementById('userDropdown');
        if (btn && dd && !btn.contains(e.target)) {
            dd.style.display = 'none';
        }
    });

    function getCookie(name) {
        return document.cookie.split('; ').reduce(function(r, v) {
            var parts = v.split('=');
            return parts[0] === name ? decodeURIComponent(parts[1]) : r;
        }, null);
    }
    function dismissBanner() {
        document.getElementById('welcomeBanner').style.display = 'none';
        document.cookie = 'banner_dismissed=true; path=/; max-age=3600';
    }
    window.addEventListener('DOMContentLoaded', function() {
        var visited  = getCookie('hack_visited');
        var dismissed = getCookie('banner_dismissed');
        var lastEmail = getCookie('last_contact_name');
        var banner = document.getElementById('welcomeBanner');
        var text   = document.getElementById('welcomeBannerText');
        if (visited && !dismissed) {
            if (lastEmail) {
                text.textContent = 'Welcome back, ' + lastEmail + '! Great to see you again.';
            } else {
                text.textContent = 'Welcome back to HACK Club!';
            }
            banner.style.display = 'flex';
            setTimeout(function() { banner.style.display = 'none'; }, 6000);
        }
        if (!visited) {
            document.cookie = 'hack_visited=true; path=/; max-age=' + (365*24*3600);
        }
    });
    </script>

</body>

</html>