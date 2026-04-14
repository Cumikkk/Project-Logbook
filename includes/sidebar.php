<!-- Backdrop Overlay (Mobile/Tablet Only) -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<!-- Sidebar -->
<div class="sidebar" id="mainSidebar">

    <!-- Close Button (Mobile/Tablet Only) -->
    <button class="sidebar-close d-lg-none" id="sidebarClose">
        <i class="bi bi-x-lg"></i>
    </button>

    <div class="sidebar-brand text-center">
        <?php if (!empty($_SESSION['foto_profil'])): ?>
            <img src="<?= BASE_URL ?>uploads/foto-profil/<?= htmlspecialchars($_SESSION['foto_profil']) ?>"
                width="70" height="70" class="rounded-circle mb-2 border border-white border-3"
                style="object-fit:cover;" alt="Foto Profil">
        <?php else: ?>
            <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=<?= $_SESSION['username'] ?>"
                width="70" class="rounded-circle mb-2 border border-white border-3" alt="Avatar">
        <?php endif; ?>
        <h5 class="fw-bold text-white mb-0"><?= $_SESSION['nama'] ?></h5>
        <small class="text-white-50">@<?= $_SESSION['username'] ?></small>
    </div>

    <?php
    $current_page = basename($_SERVER['PHP_SELF']);
    $current_dir  = basename(dirname($_SERVER['PHP_SELF']));
    $role         = $_SESSION['role'];

    $pengguna_aktif = in_array($current_page, ['admin-manajer.php', 'intern.php']) && $current_dir === 'admin';
    ?>

    <ul class="nav nav-pills flex-column mb-auto sidebar-menu">

        <!-- Dashboard -->
        <li class="nav-item">
            <a href="<?= BASE_URL ?>role/<?= $role ?>/dashboard.php"
                class="nav-link <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
                <i class="bi bi-speedometer2 me-2"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <?php if ($role === 'admin'): ?>

            <!-- Menu Admin -->
            <li class="nav-item">
                <a href="<?= BASE_URL ?>role/admin/divisi.php"
                    class="nav-link <?= ($current_page == 'divisi.php') ? 'active' : '' ?>">
                    <i class="bi bi-diagram-3-fill me-2"></i>
                    <span>Divisi</span>
                </a>
            </li>

            <!-- Pengguna (collapsible) -->
            <li class="nav-item">
                <a href="#menuPengguna"
                    class="nav-link d-flex align-items-center justify-content-between <?= $pengguna_aktif ? 'parent-open' : '' ?>"
                    data-bs-toggle="collapse"
                    aria-expanded="<?= $pengguna_aktif ? 'true' : 'false' ?>"
                    aria-controls="menuPengguna">
                    <span>
                        <i class="bi bi-people-fill me-2"></i>Pengguna
                    </span>
                    <i class="bi bi-chevron-right sidebar-arrow"></i>
                </a>
                <div class="collapse <?= $pengguna_aktif ? 'show' : '' ?>" id="menuPengguna">
                    <ul class="nav flex-column sidebar-submenu">
                        <li class="nav-item">
                            <a href="<?= BASE_URL ?>role/admin/admin-manajer.php"
                                class="nav-link <?= ($current_page == 'admin-manajer.php') ? 'active' : '' ?>">
                                <i class="bi bi-person-gear me-2"></i>Admin & Manajer
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= BASE_URL ?>role/admin/intern.php"
                                class="nav-link <?= ($current_page == 'intern.php' && $current_dir === 'admin') ? 'active' : '' ?>">
                                <i class="bi bi-person-badge-fill me-2"></i>Intern
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="nav-item">
                <a href="<?= BASE_URL ?>role/admin/cadangan.php"
                    class="nav-link <?= ($current_page == 'cadangan.php') ? 'active' : '' ?>">
                    <i class="bi bi-trash2-fill me-2"></i>
                    <span>Cadangan</span>
                </a>
            </li>

        <?php elseif ($role === 'manajer'): ?>

            <!-- Menu Manajer -->
            <li class="nav-item">
                <a href="<?= BASE_URL ?>role/manajer/intern.php"
                    class="nav-link <?= ($current_page == 'intern.php') ? 'active' : '' ?>">
                    <i class="bi bi-person-badge-fill me-2"></i>
                    <span>Intern</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?= BASE_URL ?>role/manajer/logbook.php"
                    class="nav-link <?= ($current_page == 'logbook.php') ? 'active' : '' ?>">
                    <i class="bi bi-journal-text me-2"></i>
                    <span>Logbook Intern</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?= BASE_URL ?>role/manajer/cadangan.php"
                    class="nav-link <?= ($current_page == 'cadangan.php') ? 'active' : '' ?>">
                    <i class="bi bi-trash2-fill me-2"></i>
                    <span>Cadangan</span>
                </a>
            </li>

        <?php elseif ($role === 'intern'): ?>

            <!-- Menu Intern -->
            <li class="nav-item">
                <a href="<?= BASE_URL ?>role/intern/logbook.php"
                    class="nav-link <?= ($current_page == 'logbook.php') ? 'active' : '' ?>">
                    <i class="bi bi-journal-text me-2"></i>
                    <span>Logbook</span>
                </a>
            </li>

        <?php endif; ?>

        <!-- Profil (semua role) -->
        <li class="nav-item">
            <a href="<?= BASE_URL ?>role/<?= $role ?>/profil.php"
                class="nav-link <?= ($current_page == 'profil.php') ? 'active' : '' ?>">
                <i class="bi bi-person-circle me-2"></i>
                <span>Profil</span>
            </a>
        </li>

    </ul>

</div>

<div class="content p-4 w-100">

    <style>
        /* TOP HEADER */
        .top-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: linear-gradient(90deg, #1E88B7, #5BAE3C);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 15px;
            z-index: 1030;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .hamburger-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px 10px;
            transition: transform 0.2s ease;
        }

        .hamburger-btn:hover {
            transform: scale(1.1);
        }

        .header-brand {
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
            font-size: 1.1rem;
        }

        .header-logout {
            color: white;
            font-size: 1.5rem;
            text-decoration: none;
            padding: 5px 10px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
        }

        .header-logout:hover {
            color: #FFD700;
            transform: scale(1.1);
        }

        body {
            padding-top: 60px;
        }

        /* SIDEBAR BACKDROP */
        .sidebar-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1050;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .sidebar-backdrop.active {
            opacity: 1;
            visibility: visible;
        }

        /* SIDEBAR */
        .sidebar {
            width: 260px;
            height: 100vh;
            background: linear-gradient(180deg, #1E88B7, #5BAE3C);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1040;
            transition: transform 0.3s ease;
            padding: 20px 15px;
            overflow-y: auto;
        }

        .sidebar-brand {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar-brand img {
            transition: transform 0.3s ease;
        }

        .sidebar-brand img:hover {
            transform: scale(1.05);
        }

        .sidebar-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            z-index: 10;
        }

        .sidebar-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .sidebar-menu .nav-link {
            color: #ffffff;
            margin-bottom: 10px;
            border-radius: 10px;
            padding: 10px 15px;
            font-weight: 500;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
        }

        .sidebar-menu .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.15);
            transform: translateX(4px);
        }

        .sidebar-menu .nav-link.active {
            background-color: #ffffff;
            color: #1E88B7;
            font-weight: 600;
        }

        /* SUBMENU */
        .sidebar-submenu {
            padding-left: 12px;
            margin-bottom: 4px;
        }

        .sidebar-submenu .nav-link {
            color: rgba(255, 255, 255, 0.85);
            margin-bottom: 4px;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.9rem;
            font-weight: 400;
        }

        .sidebar-submenu .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            transform: translateX(4px);
        }

        .sidebar-submenu .nav-link.active {
            background-color: #ffffff;
            color: #1E88B7;
            font-weight: 600;
        }

        /* ARROW ICON */
        .sidebar-arrow {
            font-size: 0.8rem;
            transition: transform 0.25s ease;
            flex-shrink: 0;
        }

        .nav-link[aria-expanded="true"] .sidebar-arrow {
            transform: rotate(90deg);
        }

        /* PARENT OPEN STATE */
        .nav-link.parent-open {
            background-color: rgba(255, 255, 255, 0.1);
        }

        /* CONTENT AREA */
        .content {
            margin-left: 260px;
        }

        /* DESKTOP */
        @media (min-width: 992px) {
            .top-header {
                left: 260px;
                z-index: 1020;
                border-left: 3px solid rgba(255, 255, 255, 0.3);
            }

            .sidebar {
                z-index: 1040;
                box-shadow: 2px 0 8px rgba(0, 0, 0, 0.15);
            }

            .sidebar-backdrop {
                display: none !important;
            }
        }

        /* MOBILE & TABLET */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1060;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .content {
                margin-left: 0;
                width: 100% !important;
                padding: 15px !important;
            }

            .top-header {
                left: 0;
                z-index: 1040;
                border-left: none;
            }
        }
    </style>

    <script>
        document.addEventListener("DOMContentLoaded", function() {

            const sidebar = document.getElementById("mainSidebar");
            const backdrop = document.getElementById("sidebarBackdrop");
            const toggleBtn = document.getElementById("sidebarToggle");
            const closeBtn = document.getElementById("sidebarClose");

            function openSidebar() {
                sidebar.classList.add("active");
                backdrop.classList.add("active");
                document.body.style.overflow = "hidden";
            }

            function closeSidebar() {
                sidebar.classList.remove("active");
                backdrop.classList.remove("active");
                document.body.style.overflow = "";
            }

            if (toggleBtn) toggleBtn.addEventListener("click", openSidebar);
            if (closeBtn) closeBtn.addEventListener("click", closeSidebar);
            if (backdrop) backdrop.addEventListener("click", closeSidebar);

            if (window.innerWidth < 992) {
                document.querySelectorAll(".sidebar-menu .nav-link:not([data-bs-toggle])").forEach(link => {
                    link.addEventListener("click", function() {
                        setTimeout(closeSidebar, 200);
                    });
                });
            }

            // ARROW ROTATE saat collapse event
            const menuPengguna = document.getElementById("menuPengguna");
            if (menuPengguna) {
                const trigger = document.querySelector('[href="#menuPengguna"]');
                menuPengguna.addEventListener("show.bs.collapse", function() {
                    if (trigger) trigger.classList.add("parent-open");
                });
                menuPengguna.addEventListener("hide.bs.collapse", function() {
                    if (trigger) trigger.classList.remove("parent-open");
                });
            }

        });
    </script>