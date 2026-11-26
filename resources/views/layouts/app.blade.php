<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'MBG NutriView') - SPPG Ujung Lare Parepare</title>

    <!-- Google Font: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- CollabFlow TNC Blue Website Palette Design System -->
    <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ filemtime(public_path('css/style.css')) }}">

    <!-- Tailwind CSS (Utility Layer) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'sans-serif'],
                    },
                    colors: {
                        palette: {
                            black: '#000000',
                            teal: '#164863',
                            indigo: '#5027EF',
                            gray: '#666666',
                            amber: '#FFBD17',
                            white: '#FFFFFF',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-[#F7F6F2] text-[#000000] min-h-screen antialiased font-sans">
    <div class="app-shell">
        @auth
        <!-- Mobile Top Navigation Header (< 1024px) -->
        <header class="mobile-topbar">
            <div class="flex items-center gap-3 min-w-0">
                <button type="button" id="mobile-menu-toggle" class="mobile-menu-btn" aria-label="Buka menu navigasi" aria-expanded="false">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <img src="{{ asset('images/logo-mbg.png') }}" alt="Logo Badan Gizi Nasional" class="w-9 h-9 object-contain shrink-0 rounded-xl bg-[#5027EF]/10 p-1">
                <div class="min-w-0">
                    <div class="font-extrabold text-sm text-[#000000] truncate">MBG NutriView</div>
                    <div class="text-[11px] text-[#666666] truncate">
                        {{ auth()->user()->isAdmin() ? 'Admin SPPG' : 'Ahli Gizi SPPG' }} &middot; Ujung Lare
                    </div>
                </div>
            </div>
            <a href="{{ route('public.index') }}" class="px-3.5 py-1.5 rounded-full bg-[#FFBD17] text-[11px] text-[#000000] font-extrabold shrink-0 hover:brightness-95 transition shadow-sm">
                Portal ↗
            </a>
        </header>

        <!-- Drawer Backdrop (Mobile/Tablet) -->
        <div id="sidebar-backdrop" class="sidebar-backdrop" onclick="toggleSidebar(false)"></div>

        <!-- APP SIDEBAR -->
        <aside id="app-sidebar" class="app-sidebar" aria-label="Menu Utama">
            <div>
                <!-- Header Brand & Seal -->
                <div class="flex items-start justify-between gap-2">
                    <div class="sidebar-brand">
                        <img src="{{ asset('images/logo-mbg.png') }}" alt="Logo Badan Gizi Nasional" class="w-10 h-10 object-contain shrink-0">
                        <div>
                            <div class="brand-title">MBG NutriView</div>
                            <div class="brand-sub">SPPG Ujung Lare</div>
                            <div class="brand-sub">Kota Parepare</div>
                        </div>
                    </div>
                    <button type="button" onclick="toggleSidebar(false)" class="lg:hidden p-1.5 rounded-xl text-white/80 hover:text-white hover:bg-white/10" aria-label="Tutup menu">
                        ✕
                    </button>
                </div>

                <!-- Role Pill (#FFBD17 Golden Pill with #000000 Text) -->
                <div>
                    @if(auth()->user()->isAdmin())
                        <span class="role-pill">
                            <span class="w-2 h-2 rounded-full bg-[#5027EF]"></span>
                            Akun Admin
                        </span>
                    @else
                        <span class="role-pill">
                            <span class="w-2 h-2 rounded-full bg-[#164863]"></span>
                            Akun Ahli Gizi
                        </span>
                    @endif
                </div>

                <!-- Nav Links -->
                <nav class="sidebar-nav">
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                            Dashboard
                        </a>
                        <a href="{{ route('admin.menu.index') }}" class="nav-item {{ request()->routeIs('admin.menu.*') ? 'active' : '' }}">
                            Data Menu
                        </a>
                        <a href="{{ route('admin.bahan.index') }}" class="nav-item {{ request()->routeIs('admin.bahan.*') ? 'active' : '' }}">
                            Bahan Baku
                        </a>
                        <a href="{{ route('admin.penerima.index') }}" class="nav-item {{ request()->routeIs('admin.penerima.*') ? 'active' : '' }}">
                            Penerima dan Porsi
                        </a>
                        <a href="{{ route('admin.jadwal.index') }}" class="nav-item {{ request()->routeIs('admin.jadwal.*') ? 'active' : '' }}">
                            Jadwal Produksi
                        </a>
                        <a href="{{ route('admin.tahap.index') }}" class="nav-item {{ request()->routeIs('admin.tahap.*') ? 'active' : '' }}">
                            Pencatatan Tahap
                        </a>
                        <a href="{{ route('admin.prediksi.index') }}" class="nav-item {{ request()->routeIs('admin.prediksi.*') ? 'active' : '' }}">
                            Prediksi Risiko
                        </a>
                        <a href="{{ route('admin.kpi.index') }}" class="nav-item {{ request()->routeIs('admin.kpi.*') ? 'active' : '' }}">
                            KPI Mingguan
                        </a>
                        <a href="{{ route('admin.rbac.index') }}" class="nav-item {{ request()->routeIs('admin.rbac.*') ? 'active' : '' }}">
                            Manajemen Pengguna
                        </a>
                    @else
                        <a href="{{ route('gizi.dashboard') }}" class="nav-item {{ request()->routeIs('gizi.dashboard') ? 'active' : '' }}">
                            Dashboard / Data Gizi
                        </a>
                        <a href="{{ route('gizi.hitung.validasi') }}" class="nav-item {{ request()->routeIs('gizi.hitung.validasi') ? 'active' : '' }}">
                            Hitung &amp; Validasi
                        </a>
                        <a href="{{ route('gizi.menu.lihat') }}" class="nav-item {{ request()->routeIs('gizi.menu.lihat') ? 'active' : '' }}">
                            Menu dan Bahan (lihat)
                        </a>
                        <a href="{{ route('gizi.prediksi.lihat') }}" class="nav-item {{ request()->routeIs('gizi.prediksi.lihat') ? 'active' : '' }}">
                            Prediksi Risiko
                        </a>
                        <a href="{{ route('gizi.kpi.lihat') }}" class="nav-item {{ request()->routeIs('gizi.kpi.lihat') ? 'active' : '' }}">
                            KPI Mingguan
                        </a>
                    @endif
                </nav>
            </div>

            <!-- Sidebar Footer -->
            <div class="sidebar-footer">
                <div class="access-box">
                    <strong>Hak akses</strong>
                    @if(auth()->user()->isAdmin())
                        Kelola operasional. Validasi dan publikasi oleh Ahli Gizi.
                    @else
                        Kelola gizi, validasi dan publikasi. Operasional hanya baca.
                    @endif
                </div>

                <div class="pt-1 space-y-2.5">
                    <a href="{{ route('public.index') }}" class="footer-link">
                        <span>Portal publik</span>
                        <span>↗</span>
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-xs text-white/80 hover:text-[#FFBD17] font-bold transition cursor-pointer bg-transparent border-0 p-0 flex items-center gap-1.5">
                            <span>Keluar dari akun</span>
                        </button>
                    </form>
                </div>
            </div>
        </aside>
        @endauth

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <div class="main-inner">
                @if(session('success'))
                    <div class="alert-box alert-success flex items-start justify-between gap-3 mb-5">
                        <div class="flex items-start gap-2">
                            <span class="font-extrabold shrink-0">✓ Sukses:</span>
                            <span>{{ session('success') }}</span>
                        </div>
                        <button type="button" onclick="this.parentElement.remove()" class="opacity-70 hover:opacity-100 text-sm font-bold shrink-0" aria-label="Tutup">&times;</button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert-box alert-danger flex items-start justify-between gap-3 mb-5">
                        <div class="flex items-start gap-2">
                            <span class="font-extrabold shrink-0">⚠️ Gagal:</span>
                            <span>{{ session('error') }}</span>
                        </div>
                        <button type="button" onclick="this.parentElement.remove()" class="opacity-70 hover:opacity-100 text-sm font-bold shrink-0" aria-label="Tutup">&times;</button>
                    </div>
                @endif

                @if(session('warning'))
                    <div class="alert-box alert-warning flex items-start justify-between gap-3 mb-5">
                        <div class="flex items-start gap-2">
                            <span class="font-extrabold shrink-0">⚡ Peringatan:</span>
                            <span>{{ session('warning') }}</span>
                        </div>
                        <button type="button" onclick="this.parentElement.remove()" class="opacity-70 hover:opacity-100 text-sm font-bold shrink-0" aria-label="Tutup">&times;</button>
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert-box alert-danger mb-5">
                        <div class="font-extrabold mb-1">⚠️ Periksa isian berikut:</div>
                        <ul class="list-disc list-inside text-xs space-y-0.5">
                            @foreach($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>

    <!-- Centered Custom Modal Popup -->
    <div id="mbg-modal-backdrop" class="mbg-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="mbg-modal-title" onclick="if(event.target === this) mbgCloseModal()">
        <div class="mbg-modal-card">
            <div id="mbg-modal-header" class="mbg-modal-header">
                <div id="mbg-modal-icon" class="mbg-modal-icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div>
                    <span id="mbg-modal-badge" class="mbg-modal-badge">Konfirmasi</span>
                    <h3 id="mbg-modal-title" class="mbg-modal-title">Konfirmasi Tindakan</h3>
                </div>
            </div>
            <div id="mbg-modal-body" class="mbg-modal-body"></div>
            <div class="mbg-modal-footer">
                <button type="button" id="mbg-modal-cancel" class="mbg-modal-btn mbg-modal-btn-cancel" onclick="mbgCloseModal()">
                    Batal
                </button>
                <button type="button" id="mbg-modal-confirm" class="mbg-modal-btn mbg-modal-btn-confirm">
                    Ya, Lanjutkan
                </button>
            </div>
        </div>
    </div>

    <script>
        let mbgModalAction = null;

        function mbgOpenModal(options) {
            const backdrop = document.getElementById('mbg-modal-backdrop');
            const badgeEl = document.getElementById('mbg-modal-badge');
            const titleEl = document.getElementById('mbg-modal-title');
            const bodyEl = document.getElementById('mbg-modal-body');
            const cancelBtn = document.getElementById('mbg-modal-cancel');
            const confirmBtn = document.getElementById('mbg-modal-confirm');
            const iconEl = document.getElementById('mbg-modal-icon');
            if (!backdrop) return;

            const variant = options.variant || 'danger';
            const isAlert = Boolean(options.isAlert);

            badgeEl.textContent = options.badge || (variant === 'danger' ? 'Konfirmasi Hapus' : (isAlert ? 'Informasi' : 'Konfirmasi'));
            titleEl.textContent = options.title || (variant === 'danger' ? 'Hapus Data Ini?' : (isAlert ? 'Pemberitahuan' : 'Konfirmasi Tindakan'));
            bodyEl.textContent = options.message || '';

            if (variant === 'danger') {
                iconEl.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>';
            } else if (variant === 'success') {
                iconEl.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
            } else {
                iconEl.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
            }

            cancelBtn.style.display = isAlert ? 'none' : 'inline-flex';
            cancelBtn.textContent = options.cancelText || 'Batal';
            confirmBtn.textContent = options.confirmText || (isAlert ? 'Mengerti' : (variant === 'danger' ? 'Ya, Hapus' : 'Ya, Lanjutkan'));
            confirmBtn.className = 'mbg-modal-btn ' + (variant === 'danger' && !isAlert ? 'mbg-modal-btn-danger' : 'mbg-modal-btn-confirm');

            mbgModalAction = typeof options.onConfirm === 'function' ? options.onConfirm : null;
            confirmBtn.onclick = function () {
                const cb = mbgModalAction;
                mbgCloseModal();
                if (cb) cb();
            };

            backdrop.classList.add('open');
            setTimeout(() => confirmBtn.focus(), 40);
        }

        function mbgCloseModal() {
            const backdrop = document.getElementById('mbg-modal-backdrop');
            if (backdrop) backdrop.classList.remove('open');
            mbgModalAction = null;
        }

        function mbgConfirmForm(event, message, title, variant) {
            const form = event.target;
            if (form.dataset.mbgConfirmed === '1') {
                form.dataset.mbgConfirmed = '';
                return true;
            }
            event.preventDefault();
            mbgOpenModal({
                title: title || 'Hapus Data Ini?',
                message: message,
                variant: variant || 'danger',
                onConfirm: function () {
                    form.dataset.mbgConfirmed = '1';
                    form.submit();
                }
            });
            return false;
        }

        function mbgAlert(message, title, variant) {
            mbgOpenModal({
                isAlert: true,
                title: title || 'Pemberitahuan Sistem',
                message: message,
                variant: variant || 'info',
                confirmText: 'Mengerti'
            });
        }

        function toggleSidebar(forceState) {
            const sidebar = document.getElementById('app-sidebar');
            const backdrop = document.getElementById('sidebar-backdrop');
            const btn = document.getElementById('mobile-menu-toggle');
            if (!sidebar || !backdrop) return;

            const isOpen = typeof forceState === 'boolean' ? forceState : !sidebar.classList.contains('open');
            sidebar.classList.toggle('open', isOpen);
            backdrop.classList.toggle('open', isOpen);
            if (btn) btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            document.body.style.overflow = isOpen ? 'hidden' : '';
        }

        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('mobile-menu-toggle');
            if (btn) btn.addEventListener('click', () => toggleSidebar());
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    mbgCloseModal();
                    toggleSidebar(false);
                }
            });
        });
    </script>

    @stack('scripts')
</body>
</html>
