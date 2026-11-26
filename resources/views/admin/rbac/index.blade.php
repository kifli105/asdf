@extends('layouts.app')

@section('title', 'Manajemen Pengguna & RBAC')

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="page-header">
        <h1 class="page-title">Manajemen Pengguna &amp; RBAC</h1>
        <p class="page-subtitle">
            Kelola akun pengguna, role, dan hak akses sistem &bull; {{ \Carbon\Carbon::now()->translatedFormat('l, d F Y') }}
        </p>
    </div>

    {{-- Stat Cards --}}
    <div class="grid-3">
        <div class="stat-card">
            <div class="stat-label">Total Pengguna</div>
            <div class="stat-value">{{ $totalUsers }}</div>
            <div class="stat-sub">Seluruh akun aktif di sistem</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Admin SPPG</div>
            <div class="stat-value">{{ $totalAdmin }}</div>
            <div class="stat-sub">Hak akses penuh operasional</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Ahli Gizi</div>
            <div class="stat-value">{{ $totalGizi }}</div>
            <div class="stat-sub">Hak akses validasi &amp; gizi</div>
        </div>
    </div>

    {{-- Role Matrix --}}
    <div class="card">
        <h2 class="card-title">Matriks Hak Akses Role</h2>
        <div class="overflow-x-auto">
            <table class="data-table text-xs">
                <thead>
                    <tr>
                        <th>Modul / Fitur</th>
                        <th class="text-center">Admin SPPG</th>
                        <th class="text-center">Ahli Gizi</th>
                        <th class="text-center">Publik</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $matrix = [
                            ['Manajemen Pengguna (RBAC)',     '✅ Penuh',  '—',          '—'],
                            ['Data Menu (CRUD)',              '✅ Penuh',  '👁 Baca',    '—'],
                            ['Bahan Baku (CRUD)',             '✅ Penuh',  '—',          '—'],
                            ['Penerima & Porsi (CRUD)',       '✅ Penuh',  '—',          '—'],
                            ['Jadwal Produksi (CRUD)',        '✅ Penuh',  '—',          '—'],
                            ['Pencatatan Tahap',              '✅ Penuh',  '—',          '—'],
                            ['Prediksi Risiko',               '✅ Penuh',  '👁 Baca',    '—'],
                            ['KPI Mingguan',                  '✅ Penuh',  '👁 Baca',    '—'],
                            ['Data Gizi (CRUD)',              '—',         '✅ Penuh',   '—'],
                            ['Validasi & Publikasi Menu',     '—',         '✅ Penuh',   '—'],
                            ['Hitung & Validasi Gizi',        '—',         '✅ Penuh',   '—'],
                            ['Portal Publik (QR, Alur)',      '👁 Baca',   '👁 Baca',   '👁 Baca'],
                        ];
                    @endphp
                    @foreach($matrix as $row)
                    <tr>
                        <td class="font-medium">{{ $row[0] }}</td>
                        <td class="text-center">{{ $row[1] }}</td>
                        <td class="text-center">{{ $row[2] }}</td>
                        <td class="text-center">{{ $row[3] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Search & Filter + Tambah Pengguna --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
            <h2 class="card-title mb-0">Daftar Pengguna</h2>
            <button type="button"
                onclick="document.getElementById('modal-tambah').classList.remove('hidden')"
                class="btn-primary text-sm px-4 py-2 rounded-xl font-bold flex items-center gap-2 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Pengguna
            </button>
        </div>

        {{-- Filter Bar --}}
        <form method="GET" action="{{ route('admin.rbac.index') }}" class="flex flex-col sm:flex-row gap-2 mb-4">
            <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama, username, atau email…"
                class="form-input flex-1 text-sm">
            <select name="role" class="form-input text-sm sm:w-48">
                <option value="">Semua Role</option>
                <option value="admin" {{ $roleFilter === 'admin' ? 'selected' : '' }}>Admin SPPG</option>
                <option value="ahli_gizi" {{ $roleFilter === 'ahli_gizi' ? 'selected' : '' }}>Ahli Gizi</option>
            </select>
            <button type="submit" class="btn-primary text-sm px-4 py-2 rounded-xl font-bold">Filter</button>
            @if($search || $roleFilter)
                <a href="{{ route('admin.rbac.index') }}" class="btn-secondary text-sm px-4 py-2 rounded-xl font-bold flex items-center">Reset</a>
            @endif
        </form>

        {{-- Tabel Pengguna --}}
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Dibuat</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $i => $user)
                    <tr class="{{ $user->id === auth()->id() ? 'bg-[#5027EF]/5' : '' }}">
                        <td class="text-[#666666]">{{ $users->firstItem() + $i }}</td>
                        <td class="font-semibold">
                            {{ $user->name }}
                            @if($user->id === auth()->id())
                                <span class="ml-1 text-[10px] bg-[#FFBD17] text-black font-bold px-1.5 py-0.5 rounded-full">Anda</span>
                            @endif
                        </td>
                        <td class="text-[#666666] font-mono text-xs">{{ $user->username }}</td>
                        <td class="text-[#666666] text-xs">{{ $user->email }}</td>
                        <td>
                            @if($user->role === 'admin')
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-[#5027EF]/10 text-[#5027EF]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#5027EF]"></span>
                                    Admin SPPG
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-[#164863]/10 text-[#164863]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#164863]"></span>
                                    Ahli Gizi
                                </span>
                            @endif
                        </td>
                        <td class="text-xs text-[#666666]">{{ $user->created_at?->format('d/m/Y') ?? '—' }}</td>
                        <td>
                            <div class="flex items-center justify-center gap-1.5">
                                {{-- Edit --}}
                                <button type="button"
                                    onclick="openEditModal({{ $user->id }}, '{{ addslashes($user->name) }}', '{{ $user->username }}', '{{ $user->email }}', '{{ $user->role }}')"
                                    class="p-1.5 rounded-lg text-[#5027EF] hover:bg-[#5027EF]/10 transition" title="Edit pengguna">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </button>
                                {{-- Reset Password --}}
                                <button type="button"
                                    onclick="openResetModal({{ $user->id }}, '{{ addslashes($user->name) }}')"
                                    class="p-1.5 rounded-lg text-[#FFBD17] hover:bg-[#FFBD17]/10 transition" title="Reset password">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                    </svg>
                                </button>
                                {{-- Hapus --}}
                                @if($user->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.rbac.destroy', $user) }}"
                                    onsubmit="return mbgConfirmForm(event, 'Pengguna {{ addslashes($user->name) }} akan dihapus permanen dari sistem.', 'Hapus Pengguna?', 'danger')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-lg text-red-600 hover:bg-red-50 transition" title="Hapus pengguna">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                            <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/>
                                        </svg>
                                    </button>
                                </form>
                                @else
                                <span class="p-1.5 opacity-30 cursor-not-allowed" title="Tidak bisa hapus akun sendiri">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>
                                    </svg>
                                </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-[#666666] py-8">
                            Tidak ada pengguna ditemukan
                            @if($search || $roleFilter)
                                untuk filter yang dipilih.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($users->hasPages())
        <div class="mt-4">
            {{ $users->links() }}
        </div>
        @endif
    </div>

    {{-- Audit Log --}}
    @if($auditLogs->isNotEmpty())
    <div class="card">
        <h2 class="card-title">Log Aktivitas Terkini (RBAC)</h2>
        <div class="space-y-2">
            @foreach($auditLogs as $log)
            <div class="flex items-start gap-3 py-2.5 border-b border-[#E5E5E5] last:border-0">
                <div class="shrink-0 mt-0.5">
                    @php
                        $logIcon = match($log->action) {
                            'buat_pengguna' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'icon' => '+'],
                            'hapus_pengguna' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'icon' => '−'],
                            'ubah_role' => ['bg' => 'bg-[#FFBD17]/20', 'text' => 'text-[#000]', 'icon' => '⇄'],
                            'reset_password_pengguna' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-700', 'icon' => '🔑'],
                            default => ['bg' => 'bg-[#5027EF]/10', 'text' => 'text-[#5027EF]', 'icon' => '✎'],
                        };
                    @endphp
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold {{ $logIcon['bg'] }} {{ $logIcon['text'] }}">
                        {{ $logIcon['icon'] }}
                    </span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold text-[#000000]">
                        {{ str_replace(['buat_pengguna', 'ubah_pengguna', 'hapus_pengguna', 'reset_password_pengguna', 'ubah_role'],
                                       ['Tambah Pengguna', 'Edit Pengguna', 'Hapus Pengguna', 'Reset Password', 'Ubah Role'],
                                       $log->action) }}
                        @if(isset($log->new_values['name']))
                            &ndash; <span class="font-normal">{{ $log->new_values['name'] }}</span>
                        @elseif(isset($log->old_values['name']))
                            &ndash; <span class="font-normal">{{ $log->old_values['name'] }}</span>
                        @endif
                    </div>
                    <div class="text-xs text-[#666666] mt-0.5">
                        Oleh: <span class="font-medium">{{ $log->username }}</span>
                        &bull; {{ $log->created_at->translatedFormat('d M Y, H:i') }}
                        @if($log->action === 'ubah_role' && isset($log->old_values['role'], $log->new_values['role']))
                            &bull; Role: <span class="line-through">{{ $log->old_values['role'] }}</span> → <strong>{{ $log->new_values['role'] }}</strong>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>

{{-- ============================================================ --}}
{{-- MODAL: Tambah Pengguna                                        --}}
{{-- ============================================================ --}}
<div id="modal-tambah" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
        <div class="flex items-center justify-between px-6 pt-5 pb-4 border-b border-[#E5E5E5]">
            <div>
                <span class="text-xs font-bold text-[#5027EF] uppercase tracking-wider">Tambah Pengguna</span>
                <h3 class="text-lg font-extrabold text-[#000000] mt-0.5">Buat Akun Baru</h3>
            </div>
            <button type="button" onclick="document.getElementById('modal-tambah').classList.add('hidden')"
                class="p-1.5 rounded-xl text-[#666666] hover:text-[#000000] hover:bg-[#F7F6F2] transition" aria-label="Tutup">✕</button>
        </div>
        <form method="POST" action="{{ route('admin.rbac.store') }}" class="px-6 py-5 space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="form-label">Nama Lengkap <span class="text-red-500">*</span></label>
                    <input type="text" name="name" class="form-input" placeholder="Contoh: Budi Santoso" required maxlength="100">
                </div>
                <div>
                    <label class="form-label">Username <span class="text-red-500">*</span></label>
                    <input type="text" name="username" class="form-input" placeholder="Contoh: budi.santoso" required maxlength="50">
                </div>
                <div>
                    <label class="form-label">Role <span class="text-red-500">*</span></label>
                    <select name="role" class="form-input" required>
                        <option value="admin">Admin SPPG</option>
                        <option value="ahli_gizi">Ahli Gizi</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" class="form-input" placeholder="Contoh: budi@email.com" required>
                </div>
                <div>
                    <label class="form-label">Password <span class="text-red-500">*</span></label>
                    <input type="password" name="password" class="form-input" placeholder="Min. 8 karakter" required minlength="8">
                </div>
                <div>
                    <label class="form-label">Konfirmasi Password <span class="text-red-500">*</span></label>
                    <input type="password" name="password_confirmation" class="form-input" placeholder="Ulangi password" required minlength="8">
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('modal-tambah').classList.add('hidden')"
                    class="btn-secondary text-sm px-4 py-2 rounded-xl font-bold">Batal</button>
                <button type="submit" class="btn-primary text-sm px-4 py-2 rounded-xl font-bold">Simpan Pengguna</button>
            </div>
        </form>
    </div>
</div>

{{-- ============================================================ --}}
{{-- MODAL: Edit Pengguna                                          --}}
{{-- ============================================================ --}}
<div id="modal-edit" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
        <div class="flex items-center justify-between px-6 pt-5 pb-4 border-b border-[#E5E5E5]">
            <div>
                <span class="text-xs font-bold text-[#5027EF] uppercase tracking-wider">Edit Pengguna</span>
                <h3 class="text-lg font-extrabold text-[#000000] mt-0.5">Perbarui Data Akun</h3>
            </div>
            <button type="button" onclick="document.getElementById('modal-edit').classList.add('hidden')"
                class="p-1.5 rounded-xl text-[#666666] hover:text-[#000000] hover:bg-[#F7F6F2] transition" aria-label="Tutup">✕</button>
        </div>
        <form id="form-edit" method="POST" action="" class="px-6 py-5 space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="form-label">Nama Lengkap <span class="text-red-500">*</span></label>
                    <input type="text" id="edit-name" name="name" class="form-input" required maxlength="100">
                </div>
                <div>
                    <label class="form-label">Username <span class="text-red-500">*</span></label>
                    <input type="text" id="edit-username" name="username" class="form-input" required maxlength="50">
                </div>
                <div>
                    <label class="form-label">Role <span class="text-red-500">*</span></label>
                    <select id="edit-role" name="role" class="form-input" required>
                        <option value="admin">Admin SPPG</option>
                        <option value="ahli_gizi">Ahli Gizi</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label">Email <span class="text-red-500">*</span></label>
                    <input type="email" id="edit-email" name="email" class="form-input" required>
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('modal-edit').classList.add('hidden')"
                    class="btn-secondary text-sm px-4 py-2 rounded-xl font-bold">Batal</button>
                <button type="submit" class="btn-primary text-sm px-4 py-2 rounded-xl font-bold">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

{{-- ============================================================ --}}
{{-- MODAL: Reset Password                                         --}}
{{-- ============================================================ --}}
<div id="modal-reset" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 pt-5 pb-4 border-b border-[#E5E5E5]">
            <div>
                <span class="text-xs font-bold text-[#FFBD17] uppercase tracking-wider">Reset Password</span>
                <h3 id="reset-modal-title" class="text-lg font-extrabold text-[#000000] mt-0.5">Reset Password Pengguna</h3>
            </div>
            <button type="button" onclick="document.getElementById('modal-reset').classList.add('hidden')"
                class="p-1.5 rounded-xl text-[#666666] hover:text-[#000000] hover:bg-[#F7F6F2] transition" aria-label="Tutup">✕</button>
        </div>
        <form id="form-reset" method="POST" action="" class="px-6 py-5 space-y-4">
            @csrf
            <div>
                <label class="form-label">Password Baru <span class="text-red-500">*</span></label>
                <input type="password" name="new_password" class="form-input" placeholder="Min. 8 karakter" required minlength="8">
            </div>
            <div>
                <label class="form-label">Konfirmasi Password Baru <span class="text-red-500">*</span></label>
                <input type="password" name="new_password_confirmation" class="form-input" placeholder="Ulangi password baru" required minlength="8">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('modal-reset').classList.add('hidden')"
                    class="btn-secondary text-sm px-4 py-2 rounded-xl font-bold">Batal</button>
                <button type="submit" class="bg-[#FFBD17] hover:brightness-95 text-black text-sm px-4 py-2 rounded-xl font-bold transition">Reset Password</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openEditModal(id, name, username, email, role) {
    const form = document.getElementById('form-edit');
    form.action = '/admin/manajemen-pengguna/' + id + '/update';
    document.getElementById('edit-name').value = name;
    document.getElementById('edit-username').value = username;
    document.getElementById('edit-email').value = email;
    document.getElementById('edit-role').value = role;
    document.getElementById('modal-edit').classList.remove('hidden');
}

function openResetModal(id, name) {
    const form = document.getElementById('form-reset');
    form.action = '/admin/manajemen-pengguna/' + id + '/reset-password';
    document.getElementById('reset-modal-title').textContent = 'Reset Password: ' + name;
    document.getElementById('modal-reset').classList.remove('hidden');
}

// Close on backdrop click
['modal-tambah', 'modal-edit', 'modal-reset'].forEach(function(id) {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.classList.add('hidden');
    });
});

// Close on ESC (supplement layout's global listener)
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        ['modal-tambah', 'modal-edit', 'modal-reset'].forEach(function(id) {
            document.getElementById(id).classList.add('hidden');
        });
    }
});

// Auto-open tambah modal if validation error was for a store action
@if($errors->any() && old('password'))
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('modal-tambah').classList.remove('hidden');
});
@endif
</script>
@endpush
