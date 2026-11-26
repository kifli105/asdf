<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\GiziController;
use App\Http\Controllers\PublicController;

// 1. Portal Publik (Read-Only Tanpa Login - FR-015, FR-016)
Route::get('/', [PublicController::class, 'index'])->name('public.index');
Route::get('/alur-operasional', [PublicController::class, 'alurOperasional'])->name('public.alur');
Route::get('/portal/scan', [PublicController::class, 'scanQr'])->name('public.scan.qr');

// 2. Autentikasi Pengguna (FR-001, FR-002)
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.process');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// 3. Modul Khusus Admin SPPG (Role: admin)
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    
    // Data Menu
    Route::get('/menu', [AdminController::class, 'dataMenu'])->name('menu.index');
    Route::post('/menu', [AdminController::class, 'storeMenu'])->name('menu.store');
    Route::post('/menu/{menu}/update', [AdminController::class, 'updateMenu'])->name('menu.update');
    Route::post('/menu/{menu}/kirim-validasi', [AdminController::class, 'kirimValidasi'])->name('menu.kirim_validasi');
    Route::delete('/menu/{menu}', [AdminController::class, 'destroyMenu'])->name('menu.destroy');

    // Bahan Baku
    Route::get('/bahan-baku', [AdminController::class, 'bahanBaku'])->name('bahan.index');
    Route::post('/bahan-baku', [AdminController::class, 'storeBahan'])->name('bahan.store');
    Route::post('/bahan-baku/{bahan}/update', [AdminController::class, 'updateBahan'])->name('bahan.update');
    Route::delete('/bahan-baku/{bahan}', [AdminController::class, 'destroyBahan'])->name('bahan.destroy');

    // Penerima dan Porsi
    Route::get('/penerima-porsi', [AdminController::class, 'penerimaPorsi'])->name('penerima.index');
    Route::post('/penerima-porsi', [AdminController::class, 'storeKelompok'])->name('penerima.store');
    Route::post('/penerima-porsi/{kelompok}/update', [AdminController::class, 'updateKelompok'])->name('penerima.update');
    Route::delete('/penerima-porsi/{kelompok}', [AdminController::class, 'destroyKelompok'])->name('penerima.destroy');

    // Jadwal Produksi
    Route::get('/jadwal-produksi', [AdminController::class, 'jadwalProduksi'])->name('jadwal.index');
    Route::post('/jadwal-produksi', [AdminController::class, 'storeJadwal'])->name('jadwal.store');
    Route::post('/jadwal-produksi/{jadwal}/update', [AdminController::class, 'updateJadwal'])->name('jadwal.update');
    Route::delete('/jadwal-produksi/{jadwal}', [AdminController::class, 'destroyJadwal'])->name('jadwal.destroy');

    // Pencatatan Tahap (Otomatis 1-Klik & Manual)
    Route::get('/pencatatan-tahap', [AdminController::class, 'pencatatanTahap'])->name('tahap.index');
    Route::post('/pencatatan-tahap', [AdminController::class, 'storeTahap'])->name('tahap.store');
    Route::post('/pencatatan-tahap/aksi-cepat', [AdminController::class, 'aksiCepatTahap'])->name('tahap.quick');
    Route::post('/pencatatan-tahap/titik/{titik}/update', [AdminController::class, 'updatePengantaranTitik'])->name('tahap.titik.update');

    // KPI Mingguan (Evaluasi 1x Seminggu - Terpisah dari Random Forest)
    Route::get('/kpi-mingguan', [AdminController::class, 'kpiMingguan'])->name('kpi.index');
    Route::post('/kpi-mingguan/hitung', [AdminController::class, 'hitungUlangKpi'])->name('kpi.hitung');
    Route::get('/kpi-prediksi', fn () => redirect()->route('admin.kpi.index'));

    // Prediksi Risiko Random Forest (Pra-Distribusi per Batch)
    Route::get('/prediksi-risiko', [AdminController::class, 'prediksiRisiko'])->name('prediksi.index');
    Route::post('/prediksi-risiko/hitung', [AdminController::class, 'hitungUlangPrediksi'])->name('prediksi.hitung');

    // Manajemen Pengguna & RBAC (FR-RBAC)
    Route::get('/manajemen-pengguna', [AdminController::class, 'rbacIndex'])->name('rbac.index');
    Route::post('/manajemen-pengguna', [AdminController::class, 'rbacStore'])->name('rbac.store');
    Route::post('/manajemen-pengguna/{user}/update', [AdminController::class, 'rbacUpdate'])->name('rbac.update');
    Route::delete('/manajemen-pengguna/{user}', [AdminController::class, 'rbacDestroy'])->name('rbac.destroy');
    Route::post('/manajemen-pengguna/{user}/reset-password', [AdminController::class, 'rbacResetPassword'])->name('rbac.reset_password');
});

// 4. Modul Khusus Ahli Gizi (Role: ahli_gizi)
Route::middleware(['auth', 'role:ahli_gizi'])->prefix('gizi')->name('gizi.')->group(function () {
    Route::get('/dashboard', [GiziController::class, 'dashboard'])->name('dashboard');
    
    // Master Data Gizi
    Route::post('/data-gizi', [GiziController::class, 'storeGizi'])->name('data.store');
    Route::post('/data-gizi/{gizi}/update', [GiziController::class, 'updateGizi'])->name('data.update');
    Route::delete('/data-gizi/{gizi}', [GiziController::class, 'destroyGizi'])->name('data.destroy');

    // Hitung & Validasi Gizi
    Route::get('/hitung-validasi', [GiziController::class, 'hitungValidasi'])->name('hitung.validasi');
    Route::post('/validasi/{menu}', [GiziController::class, 'validasiMenu'])->name('validasi.process');
    Route::post('/publikasikan/{menu}', [GiziController::class, 'publikasikanMenu'])->name('publikasi.process');
    Route::post('/tarik-publikasi/{menu}', [GiziController::class, 'tarikPublikasi'])->name('tarik.publikasi');

    // Menu dan Bahan (Hanya Baca)
    Route::get('/menu-bahan', [GiziController::class, 'menuBahanLihat'])->name('menu.lihat');

    // KPI Mingguan & Prediksi Risiko Terpisah (Hanya Baca)
    Route::get('/kpi-mingguan', [GiziController::class, 'kpiMingguan'])->name('kpi.lihat');
    Route::get('/prediksi-risiko', [GiziController::class, 'prediksiRisiko'])->name('prediksi.lihat');
    Route::get('/kpi-prediksi', fn () => redirect()->route('gizi.kpi.lihat'));
});
