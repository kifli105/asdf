<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Menu;
use App\Models\BahanBaku;
use App\Models\KelompokPenerima;
use App\Models\JadwalProduksi;
use App\Models\AlurOperasional;
use App\Models\PengantaranTitik;
use App\Models\KpiPrediksi;
use App\Models\AuditLog;
use App\Services\KpiService;
use App\Services\PredictionService;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AdminController extends Controller
{
    protected KpiService $kpiService;
    protected PredictionService $predictionService;

    public function __construct(KpiService $kpiService, PredictionService $predictionService)
    {
        $this->kpiService = $kpiService;
        $this->predictionService = $predictionService;
    }

    public function dashboard()
    {
        $today = Carbon::today();
        $kelompokList = KelompokPenerima::all();
        $penerimaBatch1 = $kelompokList->sum('jumlah_penerima');
        $porsiTerkemasBatch1 = $kelompokList->sum('porsi_terkemas');
        $totalPenerimaDapur = max(2911, $penerimaBatch1); // Kapasitas cakupan SPPG Ujung Lare

        $activeMenu = Menu::with(['menuBahans.bahanBaku', 'kelompokPenerima'])
            ->whereDate('tanggal_penyajian', $today)
            ->first() ?? Menu::with(['menuBahans.bahanBaku', 'kelompokPenerima'])->latest()->first();

        $alurs = collect();
        if ($activeMenu) {
            $alurs = AlurOperasional::where('menu_id', $activeMenu->id)
                ->where('batch_no', 'Tahap 1')
                ->get()
                ->keyBy('tahap');
        }

        // Peringatan bahan kedaluwarsa (berdasarkan tanggal)
        $bahanKedaluwarsa = BahanBaku::where('tanggal_kedaluwarsa', '<', $today)->get();
        $bahanMendekati = BahanBaku::whereBetween('tanggal_kedaluwarsa', [$today, $today->copy()->addDays(2)])->get();

        $kpi = null;
        if ($activeMenu) {
            $kpi = KpiPrediksi::where('menu_id', $activeMenu->id)->where('batch_no', 'Tahap 1')->first();
        }

        $jadwalHariIni = $activeMenu
            ? JadwalProduksi::where('menu_id', $activeMenu->id)->orderBy('waktu_mulai_rencana')->get()
            : collect();

        $recentAudits = AuditLog::orderByDesc('created_at')->take(5)->get();

        return view('admin.dashboard', compact(
            'totalPenerimaDapur',
            'kelompokList',
            'penerimaBatch1',
            'porsiTerkemasBatch1',
            'activeMenu',
            'alurs',
            'bahanKedaluwarsa',
            'bahanMendekati',
            'kpi',
            'jadwalHariIni',
            'recentAudits'
        ));
    }

    // --- DATA MENU ---
    public function dataMenu(Request $request)
    {
        $query = Menu::with(['kelompokPenerima', 'menuBahans.bahanBaku']);

        if ($request->filled('search')) {
            $query->where('nama_menu', 'LIKE', '%' . $request->search . '%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $menus = $query->orderBy('tanggal_penyajian', 'desc')->paginate(10)->appends($request->query());
        $kelompoks = KelompokPenerima::all();
        $bahanBakus = BahanBaku::orderBy('nama_bahan')->get();

        $editingMenu = $request->filled('edit')
            ? Menu::with(['kelompokPenerima', 'menuBahans.bahanBaku'])->find($request->edit)
            : $menus->first();
        $editingGizi = $editingMenu ? $editingMenu->hitungGiziPerSajian('besar') : null;
        $editingGiziKecil = $editingMenu ? $editingMenu->hitungGiziPerSajian('kecil') : null;

        return view('admin.menu.index', compact('menus', 'kelompoks', 'bahanBakus', 'editingMenu', 'editingGizi', 'editingGiziKecil'));
    }

    public function storeMenu(Request $request)
    {
        $validated = $request->validate([
            'nama_menu' => 'required|string|max:255',
            'tanggal_penyajian' => 'required|date',
            'jenis_makanan' => 'required|string',
            'ukuran_porsi_gram' => 'nullable|numeric|min:50',
            'ukuran_porsi_besar_gram' => 'nullable|numeric|min:50',
            'ukuran_porsi_kecil_gram' => 'nullable|numeric|min:50',
            'jumlah_porsi_rencana' => 'nullable|numeric|min:1',
            'porsi_besar_rencana' => 'nullable|numeric|min:0',
            'porsi_kecil_rencana' => 'nullable|numeric|min:0',
            'kelompok_penerima_id' => 'nullable|exists:kelompok_penerimas,id',
            'foto_menu' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'bahan_ids' => 'nullable|array',
            'jumlah_batch' => 'nullable|array',
            'satuan' => 'nullable|array',
            'komponen' => 'nullable|array',
            'berat_per_sajian' => 'nullable|array',
            'berat_porsi_besar' => 'nullable|array',
            'berat_porsi_kecil' => 'nullable|array',
        ], [
            'nama_menu.required' => 'Nama menu wajib diisi.',
            'tanggal_penyajian.required' => 'Tanggal penyajian wajib diisi.',
        ]);

        $gramBesar = (int) ($request->input('ukuran_porsi_besar_gram') ?: $request->input('ukuran_porsi_gram') ?: 450);
        $gramKecil = (int) ($request->input('ukuran_porsi_kecil_gram') ?: max(150, round($gramBesar * 0.68)));

        if ($request->filled('porsi_besar_rencana') || $request->filled('porsi_kecil_rencana')) {
            $rencanaBesar = (int) $request->input('porsi_besar_rencana', 0);
            $rencanaKecil = (int) $request->input('porsi_kecil_rencana', 0);
            $totalRencana = max(1, $rencanaBesar + $rencanaKecil);
        } else {
            $totalRencana = max(1, (int) $request->input('jumlah_porsi_rencana', 300));
            $rencanaKecil = (int) round($totalRencana * 0.35);
            $rencanaBesar = max(0, $totalRencana - $rencanaKecil);
        }

        $menuData = [
            'nama_menu' => $validated['nama_menu'],
            'tanggal_penyajian' => $validated['tanggal_penyajian'],
            'jenis_makanan' => $validated['jenis_makanan'],
            'ukuran_porsi_gram' => $gramBesar,
            'ukuran_porsi_besar_gram' => $gramBesar,
            'ukuran_porsi_kecil_gram' => $gramKecil,
            'jumlah_porsi_rencana' => $totalRencana,
            'porsi_besar_rencana' => $rencanaBesar,
            'porsi_kecil_rencana' => $rencanaKecil,
            'kelompok_penerima_id' => $validated['kelompok_penerima_id'] ?? null,
        ];

        $menuData['status'] = $request->input('action_type') === 'kirim_validasi' ? 'menunggu_validasi' : 'draft';
        if ($fotoPath = $this->handleFotoMenuUpload($request)) {
            $menuData['foto_menu'] = $fotoPath;
        }

        $menu = DB::transaction(function () use ($menuData, $request) {
            $created = Menu::create($menuData);
            $this->syncMenuBahans($created, $request);
            AuditLog::log('tambah_menu', 'menu', (string)$created->id, null, $created->toArray());

            return $created;
        });

        return redirect()->route('admin.menu.index', ['edit' => $menu->id])
            ->with('success', $menu->status === 'menunggu_validasi' ? 'Menu (Porsi Besar & Porsi Kecil) berhasil ditambahkan dan diajukan untuk validasi gizi.' : 'Menu berhasil ditambahkan sebagai Draft.');
    }

    public function updateMenu(Request $request, Menu $menu)
    {
        $validated = $request->validate([
            'nama_menu' => 'required|string|max:255',
            'tanggal_penyajian' => 'required|date',
            'jenis_makanan' => 'required|string',
            'ukuran_porsi_gram' => 'nullable|numeric|min:50',
            'ukuran_porsi_besar_gram' => 'nullable|numeric|min:50',
            'ukuran_porsi_kecil_gram' => 'nullable|numeric|min:50',
            'jumlah_porsi_rencana' => 'nullable|numeric|min:1',
            'porsi_besar_rencana' => 'nullable|numeric|min:0',
            'porsi_kecil_rencana' => 'nullable|numeric|min:0',
            'kelompok_penerima_id' => 'nullable|exists:kelompok_penerimas,id',
            'foto_menu' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'bahan_ids' => 'nullable|array',
            'jumlah_batch' => 'nullable|array',
            'satuan' => 'nullable|array',
            'komponen' => 'nullable|array',
            'berat_per_sajian' => 'nullable|array',
            'berat_porsi_besar' => 'nullable|array',
            'berat_porsi_kecil' => 'nullable|array',
        ]);

        $oldValues = $menu->toArray();
        $oldStatus = $menu->status;

        $gramBesar = (int) ($request->input('ukuran_porsi_besar_gram') ?: $request->input('ukuran_porsi_gram') ?: $menu->ukuran_porsi_besar_gram ?: 450);
        $gramKecil = (int) ($request->input('ukuran_porsi_kecil_gram') ?: $menu->ukuran_porsi_kecil_gram ?: max(150, round($gramBesar * 0.68)));

        if ($request->filled('porsi_besar_rencana') || $request->filled('porsi_kecil_rencana')) {
            $rencanaBesar = (int) $request->input('porsi_besar_rencana', 0);
            $rencanaKecil = (int) $request->input('porsi_kecil_rencana', 0);
            $totalRencana = max(1, $rencanaBesar + $rencanaKecil);
        } else {
            $totalRencana = max(1, (int) ($request->input('jumlah_porsi_rencana') ?: $menu->jumlah_porsi_rencana ?: 300));
            $rencanaKecil = (int) round($totalRencana * 0.35);
            $rencanaBesar = max(0, $totalRencana - $rencanaKecil);
        }

        $menuData = [
            'nama_menu' => $validated['nama_menu'],
            'tanggal_penyajian' => $validated['tanggal_penyajian'],
            'jenis_makanan' => $validated['jenis_makanan'],
            'ukuran_porsi_gram' => $gramBesar,
            'ukuran_porsi_besar_gram' => $gramBesar,
            'ukuran_porsi_kecil_gram' => $gramKecil,
            'jumlah_porsi_rencana' => $totalRencana,
            'porsi_besar_rencana' => $rencanaBesar,
            'porsi_kecil_rencana' => $rencanaKecil,
            'kelompok_penerima_id' => $validated['kelompok_penerima_id'] ?? null,
        ];

        if ($fotoPath = $this->handleFotoMenuUpload($request)) {
            $menuData['foto_menu'] = $fotoPath;
        }

        // FR-007: Jika menu sudah terpublikasi/tervalidasi dan bahan baku diubah, kembalikan ke menunggu_validasi
        $isBahanChanged = $request->has('bahan_ids');

        if (($oldStatus === 'terpublikasi' || $oldStatus === 'tervalidasi') && $isBahanChanged) {
            $menuData['status'] = 'menunggu_validasi';
            $menuData['is_published'] = false; // sembunyikan sementara dari portal publik (FR-007)
        } elseif ($request->input('action_type') === 'kirim_validasi' && $oldStatus === 'draft') {
            $menuData['status'] = 'menunggu_validasi';
        }

        DB::transaction(function () use ($menu, $menuData, $request, $oldValues) {
            $menu->update($menuData);
            $this->syncMenuBahans($menu, $request);
            AuditLog::log('ubah_menu', 'menu', (string)$menu->id, $oldValues, $menu->fresh()->toArray());
        });

        $msg = 'Menu berhasil diperbarui.';
        if (($oldStatus === 'terpublikasi' || $oldStatus === 'tervalidasi') && $isBahanChanged) {
            $msg .= ' Karena bahan diubah, status dikembalikan ke "Menunggu Validasi" untuk diverifikasi ulang oleh Ahli Gizi (FR-007).';
        } elseif ($request->input('action_type') === 'kirim_validasi') {
            $msg .= ' Menu berhasil diajukan untuk validasi Ahli Gizi.';
        }

        return redirect()->route('admin.menu.index', ['edit' => $menu->id])->with('success', $msg);
    }

    public function kirimValidasi(Menu $menu)
    {
        $old = $menu->toArray();
        $menu->status = 'menunggu_validasi';
        $menu->is_published = false;
        $menu->save();

        AuditLog::log('kirim_validasi', 'menu', (string)$menu->id, $old, $menu->fresh()->toArray());

        return redirect()->route('admin.menu.index', ['edit' => $menu->id])
            ->with('success', 'Menu "' . $menu->nama_menu . '" berhasil dikirim ke Ahli Gizi untuk validasi.');
    }

    public function destroyMenu(Menu $menu)
    {
        $old = $menu->toArray();
        $menu->delete();
        AuditLog::log('hapus_menu', 'menu', (string)$old['id'], $old, null);
        return redirect()->route('admin.menu.index')->with('success', 'Menu berhasil dihapus.');
    }

    // --- BAHAN BAKU ---
    public function bahanBaku(Request $request)
    {
        $today = Carbon::today();
        $query = BahanBaku::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_bahan', 'LIKE', '%' . $search . '%')
                  ->orWhere('kode_lot', 'LIKE', '%' . $search . '%')
                  ->orWhere('supplier', 'LIKE', '%' . $search . '%');
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'kedaluwarsa') {
                $query->where('tanggal_kedaluwarsa', '<', $today);
            } elseif ($request->status === 'mendekati') {
                $query->whereBetween('tanggal_kedaluwarsa', [$today, $today->copy()->addDays(2)]);
            } elseif ($request->status === 'aman') {
                $query->where('tanggal_kedaluwarsa', '>', $today->copy()->addDays(2));
            }
        }

        $bahanList = $query->orderBy('tanggal_kedaluwarsa', 'asc')->paginate(10)->appends($request->query());
        $menus = Menu::with('menuBahans.bahanBaku')->orderByDesc('tanggal_penyajian')->get();
        $selectedTraceMenu = $request->filled('menu_id')
            ? $menus->firstWhere('id', (int) $request->menu_id)
            : $menus->first();

        $allBahan = BahanBaku::all();
        $totalStokKg = $allBahan->sum('stok_kg');
        $kedaluwarsaCount = $allBahan->filter(fn($b) => $b->tanggal_kedaluwarsa && $b->tanggal_kedaluwarsa->lt($today))->count();
        $mendekatiCount = $allBahan->filter(fn($b) => $b->tanggal_kedaluwarsa && $b->tanggal_kedaluwarsa->gte($today) && $b->tanggal_kedaluwarsa->lte($today->copy()->addDays(2)))->count();
        $editingBahan = $request->filled('edit') ? BahanBaku::find($request->edit) : null;

        return view('admin.bahan.index', compact(
            'bahanList',
            'menus',
            'selectedTraceMenu',
            'totalStokKg',
            'kedaluwarsaCount',
            'mendekatiCount',
            'editingBahan'
        ));
    }

    public function storeBahan(Request $request)
    {
        $validated = $this->validateBahanRequest($request);

        $validated['is_utama'] = $request->boolean('is_utama');
        $bahan = BahanBaku::create($validated);

        AuditLog::log('tambah_bahan', 'bahan_baku', (string)$bahan->id, null, $bahan->toArray());

        return redirect()->route('admin.bahan.index')->with('success', 'Bahan baku berhasil disimpan.');
    }

    public function updateBahan(Request $request, BahanBaku $bahan)
    {
        $validated = $this->validateBahanRequest($request, $bahan->id);

        $validated['is_utama'] = $request->boolean('is_utama');
        $old = $bahan->toArray();
        $bahan->update($validated);

        AuditLog::log('ubah_bahan', 'bahan_baku', (string)$bahan->id, $old, $bahan->fresh()->toArray());

        return redirect()->route('admin.bahan.index')->with('success', 'Data bahan baku berhasil diperbarui.');
    }

    public function destroyBahan(BahanBaku $bahan)
    {
        $old = $bahan->toArray();
        $bahan->delete();
        AuditLog::log('hapus_bahan', 'bahan_baku', (string)$old['id'], $old, null);
        return redirect()->route('admin.bahan.index')->with('success', 'Bahan baku berhasil dihapus.');
    }

    // --- PENERIMA DAN PORSI (PORSI BESAR & PORSI KECIL) ---
    public function penerimaPorsi(Request $request)
    {
        $kelompokList = KelompokPenerima::orderBy('id')->get();
        $totalPenerima = $kelompokList->sum('jumlah_penerima');
        $totalPenerimaBesar = $kelompokList->sum('penerima_porsi_besar');
        $totalPenerimaKecil = $kelompokList->sum('penerima_porsi_kecil');
        $totalTerkemas = $kelompokList->sum('porsi_terkemas');
        $totalTerkemasBesar = $kelompokList->sum('terkemas_porsi_besar');
        $totalTerkemasKecil = $kelompokList->sum('terkemas_porsi_kecil');
        $editingKelompok = $request->filled('edit') ? KelompokPenerima::find($request->edit) : null;

        return view('admin.penerima.index', compact(
            'kelompokList',
            'totalPenerima',
            'totalPenerimaBesar',
            'totalPenerimaKecil',
            'totalTerkemas',
            'totalTerkemasBesar',
            'totalTerkemasKecil',
            'editingKelompok'
        ));
    }

    public function storeKelompok(Request $request)
    {
        $validated = $request->validate([
            'nama_kelompok' => 'required|string',
            'kategori' => 'required|string',
            'jumlah_penerima' => 'nullable|integer|min:1',
            'penerima_porsi_besar' => 'nullable|integer|min:0',
            'penerima_porsi_kecil' => 'nullable|integer|min:0',
            'porsi_terkemas' => 'nullable|integer|min:0',
            'terkemas_porsi_besar' => 'nullable|integer|min:0',
            'terkemas_porsi_kecil' => 'nullable|integer|min:0',
            'titik_antar' => 'required|string',
            'estimasi_waktu_tempuh_menit' => 'required|integer|min:1',
        ]);

        if ($request->filled('penerima_porsi_besar') || $request->filled('penerima_porsi_kecil')) {
            $pBesar = (int) $request->input('penerima_porsi_besar', 0);
            $pKecil = (int) $request->input('penerima_porsi_kecil', 0);
            $totalP = max(1, $pBesar + $pKecil);
        } else {
            $totalP = max(1, (int) $request->input('jumlah_penerima', 100));
            $pKecil = (int) round($totalP * 0.35);
            $pBesar = max(0, $totalP - $pKecil);
        }

        if ($request->filled('terkemas_porsi_besar') || $request->filled('terkemas_porsi_kecil')) {
            $kBesar = (int) $request->input('terkemas_porsi_besar', 0);
            $kKecil = (int) $request->input('terkemas_porsi_kecil', 0);
            $totalK = max(0, $kBesar + $kKecil);
        } else {
            $totalK = max(0, (int) $request->input('porsi_terkemas', $totalP));
            $kKecil = min($totalK, $pKecil);
            $kBesar = max(0, $totalK - $kKecil);
        }

        $payload = [
            'nama_kelompok' => $validated['nama_kelompok'],
            'kategori' => $validated['kategori'],
            'jumlah_penerima' => $totalP,
            'penerima_porsi_besar' => $pBesar,
            'penerima_porsi_kecil' => $pKecil,
            'porsi_terkemas' => $totalK,
            'terkemas_porsi_besar' => $kBesar,
            'terkemas_porsi_kecil' => $kKecil,
            'titik_antar' => $validated['titik_antar'],
            'estimasi_waktu_tempuh_menit' => $validated['estimasi_waktu_tempuh_menit'],
        ];

        $kelompok = KelompokPenerima::create($payload);
        AuditLog::log('tambah_kelompok_penerima', 'kelompok_penerima', (string)$kelompok->id, null, $kelompok->toArray());

        return redirect()->route('admin.penerima.index')->with('success', 'Kelompok penerima (Porsi Besar & Kecil) berhasil ditambahkan.');
    }

    public function updateKelompok(Request $request, KelompokPenerima $kelompok)
    {
        $validated = $request->validate([
            'nama_kelompok' => 'required|string',
            'kategori' => 'required|string',
            'jumlah_penerima' => 'nullable|integer|min:1',
            'penerima_porsi_besar' => 'nullable|integer|min:0',
            'penerima_porsi_kecil' => 'nullable|integer|min:0',
            'porsi_terkemas' => 'nullable|integer|min:0',
            'terkemas_porsi_besar' => 'nullable|integer|min:0',
            'terkemas_porsi_kecil' => 'nullable|integer|min:0',
            'titik_antar' => 'required|string',
            'estimasi_waktu_tempuh_menit' => 'required|integer|min:1',
        ]);

        if ($request->filled('penerima_porsi_besar') || $request->filled('penerima_porsi_kecil')) {
            $pBesar = (int) $request->input('penerima_porsi_besar', 0);
            $pKecil = (int) $request->input('penerima_porsi_kecil', 0);
            $totalP = max(1, $pBesar + $pKecil);
        } else {
            $totalP = max(1, (int) $request->input('jumlah_penerima', $kelompok->jumlah_penerima));
            $pKecil = (int) round($totalP * 0.35);
            $pBesar = max(0, $totalP - $pKecil);
        }

        if ($request->filled('terkemas_porsi_besar') || $request->filled('terkemas_porsi_kecil')) {
            $kBesar = (int) $request->input('terkemas_porsi_besar', 0);
            $kKecil = (int) $request->input('terkemas_porsi_kecil', 0);
            $totalK = max(0, $kBesar + $kKecil);
        } else {
            $totalK = max(0, (int) $request->input('porsi_terkemas', $kelompok->porsi_terkemas));
            $kKecil = min($totalK, $pKecil);
            $kBesar = max(0, $totalK - $kKecil);
        }

        $payload = [
            'nama_kelompok' => $validated['nama_kelompok'],
            'kategori' => $validated['kategori'],
            'jumlah_penerima' => $totalP,
            'penerima_porsi_besar' => $pBesar,
            'penerima_porsi_kecil' => $pKecil,
            'porsi_terkemas' => $totalK,
            'terkemas_porsi_besar' => $kBesar,
            'terkemas_porsi_kecil' => $kKecil,
            'titik_antar' => $validated['titik_antar'],
            'estimasi_waktu_tempuh_menit' => $validated['estimasi_waktu_tempuh_menit'],
        ];

        $old = $kelompok->toArray();
        $kelompok->update($payload);
        AuditLog::log('ubah_kelompok_penerima', 'kelompok_penerima', (string)$kelompok->id, $old, $kelompok->fresh()->toArray());

        return redirect()->route('admin.penerima.index')->with('success', 'Data kelompok penerima (Porsi Besar & Kecil) berhasil diperbarui.');
    }

    public function destroyKelompok(KelompokPenerima $kelompok)
    {
        $old = $kelompok->toArray();
        $kelompok->delete();
        AuditLog::log('hapus_kelompok_penerima', 'kelompok_penerima', (string)$old['id'], $old, null);
        return redirect()->route('admin.penerima.index')->with('success', 'Kelompok penerima berhasil dihapus.');
    }

    // --- JADWAL PRODUKSI ---
    public function jadwalProduksi(Request $request)
    {
        $query = JadwalProduksi::with(['menu', 'kelompokPenerima']);
        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        }
        if ($request->filled('batch_no')) {
            $query->where('batch_no', $request->batch_no);
        }

        $jadwalList = $query->orderBy('tanggal', 'desc')->orderBy('waktu_mulai_rencana')->get();
        $menus = Menu::orderByDesc('tanggal_penyajian')->get();
        $kelompoks = KelompokPenerima::all();
        $editingJadwal = $request->filled('edit') ? JadwalProduksi::find($request->edit) : null;

        // Deteksi ID jadwal yang beririsan untuk petugas sama
        $bentrokIds = [];
        foreach ($jadwalList as $i => $j1) {
            foreach ($jadwalList as $k => $j2) {
                if ($i >= $k) {
                    continue;
                }
                $sameDay = optional($j1->tanggal)->format('Y-m-d') === optional($j2->tanggal)->format('Y-m-d');
                if ($sameDay && $j1->petugas_rencana === $j2->petugas_rencana
                    && $j1->waktu_mulai_rencana < $j2->waktu_selesai_rencana
                    && $j1->waktu_selesai_rencana > $j2->waktu_mulai_rencana) {
                    $bentrokIds[] = $j1->id;
                    $bentrokIds[] = $j2->id;
                }
            }
        }
        $bentrokIds = array_unique($bentrokIds);

        return view('admin.jadwal.index', compact('jadwalList', 'menus', 'kelompoks', 'editingJadwal', 'bentrokIds'));
    }

    public function storeJadwal(Request $request)
    {
        $validated = $request->validate([
            'menu_id' => 'required|exists:menus,id',
            'kelompok_penerima_id' => 'required|exists:kelompok_penerimas,id',
            'batch_no' => 'required|string',
            'tanggal' => 'required|date',
            'tahap' => 'required|string',
            'waktu_mulai_rencana' => 'required',
            'waktu_selesai_rencana' => 'required',
            'petugas_rencana' => 'required|string',
            'porsi_target' => 'required|integer|min:1',
            'target_tiba' => 'nullable',
        ]);

        $newMulai = strlen($validated['waktu_mulai_rencana']) === 5 ? $validated['waktu_mulai_rencana'] . ':00' : $validated['waktu_mulai_rencana'];
        $newSelesai = strlen($validated['waktu_selesai_rencana']) === 5 ? $validated['waktu_selesai_rencana'] . ':00' : $validated['waktu_selesai_rencana'];

        // FR-022: Deteksi jadwal bentrok untuk petugas yang sama
        if ($bentrok = $this->findJadwalBentrok($validated['tanggal'], $validated['petugas_rencana'], $newMulai, $newSelesai)) {
            return back()->withInput()->with('error', "Jadwal bentrok: Petugas '{$validated['petugas_rencana']}' sudah dialokasikan pada tahap '{$bentrok->tahap}' ({$bentrok->waktu_mulai_rencana} - {$bentrok->waktu_selesai_rencana}).");
        }

        $validated['waktu_mulai_rencana'] = $newMulai;
        $validated['waktu_selesai_rencana'] = $newSelesai;
        if (!empty($validated['target_tiba']) && strlen($validated['target_tiba']) === 5) {
            $validated['target_tiba'] .= ':00';
        }
        $validated['status'] = $request->input('status', 'Direncanakan');

        DB::transaction(function () use ($validated) {
            $jadwal = JadwalProduksi::create($validated);
            AuditLog::log('tambah_jadwal', 'jadwal_produksi', (string)$jadwal->id, null, $jadwal->toArray());
        });

        return redirect()->route('admin.jadwal.index')->with('success', 'Jadwal produksi berhasil disimpan.');
    }

    public function updateJadwal(Request $request, JadwalProduksi $jadwal)
    {
        $validated = $request->validate([
            'menu_id' => 'required|exists:menus,id',
            'kelompok_penerima_id' => 'required|exists:kelompok_penerimas,id',
            'batch_no' => 'required|string',
            'tanggal' => 'required|date',
            'tahap' => 'required|string',
            'waktu_mulai_rencana' => 'required',
            'waktu_selesai_rencana' => 'required',
            'petugas_rencana' => 'required|string',
            'porsi_target' => 'required|integer|min:1',
            'target_tiba' => 'nullable',
            'status' => 'nullable|string',
        ]);

        $newMulai = strlen($validated['waktu_mulai_rencana']) === 5 ? $validated['waktu_mulai_rencana'] . ':00' : $validated['waktu_mulai_rencana'];
        $newSelesai = strlen($validated['waktu_selesai_rencana']) === 5 ? $validated['waktu_selesai_rencana'] . ':00' : $validated['waktu_selesai_rencana'];

        if ($bentrok = $this->findJadwalBentrok($validated['tanggal'], $validated['petugas_rencana'], $newMulai, $newSelesai, $jadwal->id)) {
            return back()->withInput()->with('error', "Jadwal bentrok: Petugas '{$validated['petugas_rencana']}' sudah dialokasikan pada tahap '{$bentrok->tahap}' ({$bentrok->waktu_mulai_rencana} - {$bentrok->waktu_selesai_rencana}).");
        }

        $validated['waktu_mulai_rencana'] = $newMulai;
        $validated['waktu_selesai_rencana'] = $newSelesai;
        if (!empty($validated['target_tiba']) && strlen($validated['target_tiba']) === 5) {
            $validated['target_tiba'] .= ':00';
        }

        $old = $jadwal->toArray();
        DB::transaction(function () use ($jadwal, $validated, $old) {
            $jadwal->update($validated);
            AuditLog::log('ubah_jadwal', 'jadwal_produksi', (string)$jadwal->id, $old, $jadwal->fresh()->toArray());
        });

        return redirect()->route('admin.jadwal.index')->with('success', 'Jadwal produksi berhasil diperbarui.');
    }

    public function destroyJadwal(JadwalProduksi $jadwal)
    {
        $old = $jadwal->toArray();
        $jadwal->delete();
        AuditLog::log('hapus_jadwal', 'jadwal_produksi', (string)$old['id'], $old, null);
        return redirect()->route('admin.jadwal.index')->with('success', 'Jadwal produksi berhasil dihapus.');
    }

    // --- PENCATATAN TAHAP ---
    public function pencatatanTahap(Request $request)
    {
        $today = Carbon::today();
        $menus = Menu::with('kelompokPenerima')->orderByDesc('tanggal_penyajian')->get();
        $kelompoks = KelompokPenerima::all();

        $activeMenu = $request->filled('menu_id')
            ? Menu::with(['menuBahans.bahanBaku', 'kelompokPenerima'])->find($request->menu_id)
            : (Menu::with(['menuBahans.bahanBaku', 'kelompokPenerima'])->whereDate('tanggal_penyajian', $today)->first()
                ?? Menu::with(['menuBahans.bahanBaku', 'kelompokPenerima'])->latest()->first());

        $batchNo = $request->input('batch_no', 'Tahap 1');
        $alurs = collect();
        $titiks = collect();
        $jadwalList = collect();

        if ($activeMenu) {
            $alurs = AlurOperasional::where('menu_id', $activeMenu->id)
                ->where('batch_no', $batchNo)
                ->get()
                ->keyBy('tahap');
            $alurDist = $alurs->get('pengantaran');
            if ($alurDist) {
                $titiks = PengantaranTitik::where('alur_operasional_id', $alurDist->id)->with('kelompokPenerima')->get();
            }
            $jadwalList = JadwalProduksi::where('menu_id', $activeMenu->id)
                ->where('batch_no', $batchNo)
                ->orderBy('waktu_mulai_rencana')
                ->get();
        }

        $bahanList = BahanBaku::orderBy('tanggal_kedaluwarsa')->get();

        return view('admin.tahap.index', compact(
            'menus',
            'kelompoks',
            'activeMenu',
            'batchNo',
            'alurs',
            'titiks',
            'jadwalList',
            'bahanList'
        ));
    }

    public function storeTahap(Request $request)
    {
        // FR-019: Waktu mulai, waktu selesai, dan petugas aktual wajib diisi agar pencatatan tahap sah
        $validated = $request->validate([
            'menu_id' => 'required|exists:menus,id',
            'batch_no' => 'required|string',
            'tanggal' => 'required|date',
            'tahap' => 'required|in:terima_bahan,pengolahan,pengemasan,pengantaran',
            'waktu_mulai_aktual' => 'required|string',
            'waktu_selesai_aktual' => 'required|string',
            'petugas_aktual' => 'required|string|max:255',
            'porsi_terkemas' => 'nullable|integer|min:0',
            'catatan_kendala' => 'nullable|string',
            'waktu_matang_nasi' => 'nullable|string',
            'waktu_matang_lauk' => 'nullable|string',
            'waktu_matang_sayur' => 'nullable|string',
            'waktu_matang_terakhir' => 'nullable|string',
        ], [
            'waktu_mulai_aktual.required' => 'Waktu mulai aktual wajib diisi (FR-019).',
            'waktu_selesai_aktual.required' => 'Waktu selesai aktual wajib diisi (FR-019).',
            'petugas_aktual.required' => 'Nama petugas penanggung jawab tahap wajib diisi (FR-019).',
        ]);

        $tanggal = $validated['tanggal'];
        $mulaiAt = $this->parseStageDateTime($tanggal, $request->waktu_mulai_aktual);
        $selesaiAt = $this->parseStageDateTime($tanggal, $request->waktu_selesai_aktual);

        if (!$mulaiAt || !$selesaiAt) {
            return back()->withInput()->withErrors([
                'waktu_selesai_aktual' => 'Format waktu mulai dan waktu selesai aktual tidak valid (FR-019).',
            ]);
        }

        if ($selesaiAt->lt($mulaiAt)) {
            return back()->withInput()->withErrors([
                'waktu_selesai_aktual' => 'Waktu selesai aktual tidak boleh lebih awal daripada waktu mulai aktual.',
            ]);
        }

        $matangNasi = $this->parseStageDateTime($tanggal, $request->waktu_matang_nasi);
        $matangLauk = $this->parseStageDateTime($tanggal, $request->waktu_matang_lauk);
        $matangSayur = $this->parseStageDateTime($tanggal, $request->waktu_matang_sayur);
        $matangTerakhir = $this->parseStageDateTime($tanggal, $request->waktu_matang_terakhir);

        if (!$matangTerakhir) {
            $matangCandidates = array_filter([$matangNasi, $matangLauk, $matangSayur]);
            if (!empty($matangCandidates)) {
                $matangTerakhir = collect($matangCandidates)->sortByDesc(fn($dt) => $dt->timestamp)->first();
            }
        }

        // FR-020: Urutan tahap (terima_bahan -> pengolahan -> pengemasan -> pengantaran)
        $urutan = ['terima_bahan' => 1, 'pengolahan' => 2, 'pengemasan' => 3, 'pengantaran' => 4];
        $currentOrder = $urutan[$validated['tahap']];

        if ($currentOrder > 1) {
            $prevTahapName = array_search($currentOrder - 1, $urutan);
            $prevRecorded = AlurOperasional::where('menu_id', $validated['menu_id'])
                ->where('batch_no', $validated['batch_no'])
                ->where('tahap', $prevTahapName)
                ->whereNotNull('waktu_selesai_aktual')
                ->first();

            if (!$prevRecorded) {
                session()->flash('warning', "Peringatan urutan tahap (FR-020): Tahap sebelumnya ({$prevTahapName}) belum tercatat selesai.");
            }
        }

        // FR-026: Tandai tahap terlambat jika melewati target jadwal + toleransi T
        $isTerlambat = $this->isTahapTerlambat(
            (int) $validated['menu_id'],
            $validated['batch_no'],
            $tanggal,
            $validated['tahap'],
            $selesaiAt
        );

        $payload = [
            'tanggal' => $tanggal,
            'waktu_mulai_aktual' => $mulaiAt,
            'waktu_selesai_aktual' => $selesaiAt,
            'petugas_aktual' => $validated['petugas_aktual'],
            'porsi_terkemas' => $validated['porsi_terkemas'] ?? null,
            'catatan_kendala' => $validated['catatan_kendala'] ?? null,
            'status_tahap' => $isTerlambat ? 'terlambat' : 'selesai',
        ];

        if ($matangNasi) $payload['waktu_matang_nasi'] = $matangNasi;
        if ($matangLauk) $payload['waktu_matang_lauk'] = $matangLauk;
        if ($matangSayur) $payload['waktu_matang_sayur'] = $matangSayur;
        if ($matangTerakhir) $payload['waktu_matang_terakhir'] = $matangTerakhir;

        $alur = DB::transaction(function () use ($validated, $payload, $mulaiAt, $selesaiAt) {
            $record = AlurOperasional::updateOrCreate(
                [
                    'menu_id' => $validated['menu_id'],
                    'batch_no' => $validated['batch_no'],
                    'tahap' => $validated['tahap'],
                ],
                $payload
            );

            // Pastikan titik pengantaran tersedia ketika mencatat pengantaran
            if ($validated['tahap'] === 'pengantaran' && $record->pengantaranTitiks()->count() === 0) {
                foreach (KelompokPenerima::all() as $kel) {
                    $bDikirim = $kel->terkemas_porsi_besar ?: $kel->penerima_porsi_besar;
                    $kDikirim = $kel->terkemas_porsi_kecil ?: $kel->penerima_porsi_kecil;
                    $totDikirim = ($bDikirim + $kDikirim) > 0 ? ($bDikirim + $kDikirim) : ($kel->porsi_terkemas ?: $kel->jumlah_penerima);
                    PengantaranTitik::create([
                        'alur_operasional_id' => $record->id,
                        'kelompok_penerima_id' => $kel->id,
                        'petugas_antar' => $validated['petugas_aktual'],
                        'porsi_dikirim' => $totDikirim,
                        'porsi_besar_dikirim' => $bDikirim,
                        'porsi_kecil_dikirim' => $kDikirim,
                        'waktu_berangkat_aktual' => $mulaiAt,
                        'waktu_tiba_aktual' => $selesaiAt,
                        'status_antar' => 'Diterima',
                    ]);
                }
            }

            AuditLog::log('catat_tahap', 'alur_operasional', (string)$record->id, null, $record->toArray());

            return $record;
        });

        $menu = Menu::find($validated['menu_id']);

        // Pemicu otomatis pra-distribusi khusus Random Forest (KPI dievaluasi terpisah 1x seminggu):
        if (in_array($validated['tahap'], ['pengemasan', 'pengantaran'], true) && $selesaiAt) {
            $this->predictionService->jalankanPrediksi($menu, $validated['batch_no']);
        }

        $msg = "Tahap {$validated['tahap']} berhasil dicatat.";
        if ($isTerlambat) {
            $msg .= ' Status ditandai TERLAMBAT karena melewati target batas waktu jadwal + toleransi.';
        }

        return redirect()->route('admin.tahap.index', ['menu_id' => $validated['menu_id'], 'batch_no' => $validated['batch_no']])
            ->with('success', $msg);
    }

    /**
     * Pencatatan Tahap Otomatis 1-Klik (Mulai Sekarang / Selesai Sekarang & Hitung Durasi Otomatis)
     */
    public function aksiCepatTahap(Request $request)
    {
        $validated = $request->validate([
            'menu_id' => 'required|exists:menus,id',
            'batch_no' => 'required|string',
            'tahap' => 'required|in:terima_bahan,pengolahan,pengemasan,pengantaran',
            'aksi' => 'required|in:mulai,selesai,komponen',
            'komponen' => 'nullable|in:nasi,lauk,sayur',
        ]);

        $menu = Menu::findOrFail($validated['menu_id']);
        $batchNo = $validated['batch_no'];
        $tahap = $validated['tahap'];
        $now = Carbon::now();
        $tanggal = optional($menu->tanggal_penyajian)->format('Y-m-d') ?: $now->toDateString();

        $labelMap = [
            'terima_bahan' => 'Terima bahan',
            'pengolahan' => 'Olah',
            'pengemasan' => 'Kemas',
            'pengantaran' => 'Distribusi',
        ];
        $tahapLabel = $labelMap[$tahap] ?? $tahap;

        // Ambil nama petugas dari jadwal rencana jika tersedia, atau nama akun login
        $jadwalTerkait = JadwalProduksi::where('menu_id', $menu->id)
            ->where('batch_no', $batchNo)
            ->where('tahap', 'LIKE', '%' . $tahap . '%')
            ->first();
        $defaultPetugas = $jadwalTerkait?->petugas_rencana ?: (auth()->user()?->name ?: 'Petugas Dapur SPPG');

        $existing = AlurOperasional::where('menu_id', $menu->id)
            ->where('batch_no', $batchNo)
            ->where('tahap', $tahap)
            ->first();

        if ($validated['aksi'] === 'komponen' && !empty($validated['komponen'])) {
            $komp = $validated['komponen'];
            $matangAt = Carbon::parse($tanggal . ' ' . $now->format('H:i:s'));
            $field = 'waktu_matang_' . $komp;

            $olahRec = AlurOperasional::firstOrNew([
                'menu_id' => $menu->id,
                'batch_no' => $batchNo,
                'tahap' => 'pengolahan',
            ]);
            if (!$olahRec->exists) {
                $olahRec->tanggal = $tanggal;
                $olahRec->waktu_mulai_aktual = $matangAt;
                $olahRec->petugas_aktual = $defaultPetugas;
                $olahRec->porsi_terkemas = $menu->jumlah_porsi_rencana;
                $olahRec->status_tahap = 'berlangsung';
            }
            $olahRec->{$field} = $matangAt;

            $candidates = array_filter([
                $olahRec->waktu_matang_nasi ? Carbon::parse($olahRec->waktu_matang_nasi) : null,
                $olahRec->waktu_matang_lauk ? Carbon::parse($olahRec->waktu_matang_lauk) : null,
                $olahRec->waktu_matang_sayur ? Carbon::parse($olahRec->waktu_matang_sayur) : null,
            ]);
            $olahRec->waktu_matang_terakhir = collect($candidates)->sortByDesc(fn ($dt) => $dt->timestamp)->first();
            $olahRec->save();

            AuditLog::log('matang_komponen_cepat', 'alur_operasional', (string) $olahRec->id, null, $olahRec->toArray());

            return redirect()->route('admin.tahap.index', ['menu_id' => $menu->id, 'batch_no' => $batchNo])
                ->with('success', 'Waktu matang ' . ucfirst($komp) . ' dicatat otomatis pukul ' . $matangAt->format('H.i') . ' WITA (Batas konsumsi aman: ' . $matangAt->copy()->addHours(4)->format('H.i') . ' WITA).');
        }

        if ($validated['aksi'] === 'mulai') {
            $mulaiAt = Carbon::parse($tanggal . ' ' . $now->format('H:i:s'));
            $record = DB::transaction(function () use ($menu, $batchNo, $tahap, $tanggal, $mulaiAt, $existing, $defaultPetugas) {
                $rec = AlurOperasional::updateOrCreate(
                    [
                        'menu_id' => $menu->id,
                        'batch_no' => $batchNo,
                        'tahap' => $tahap,
                    ],
                    [
                        'tanggal' => $tanggal,
                        'waktu_mulai_aktual' => $mulaiAt,
                        'waktu_selesai_aktual' => null,
                        'petugas_aktual' => $existing?->petugas_aktual ?: $defaultPetugas,
                        'porsi_terkemas' => $existing?->porsi_terkemas ?: $menu->jumlah_porsi_rencana,
                        'status_tahap' => 'berlangsung',
                    ]
                );
                AuditLog::log('mulai_tahap_cepat', 'alur_operasional', (string) $rec->id, null, $rec->toArray());
                return $rec;
            });

            return redirect()->route('admin.tahap.index', ['menu_id' => $menu->id, 'batch_no' => $batchNo])
                ->with('success', "Tahap {$tahapLabel} dimulai otomatis pukul {$mulaiAt->format('H.i')} WITA. Klik 'Selesai Sekarang' saat tahap rampung.");
        }

        // Aksi = selesai -> catat waktu selesai otomatis & hitung durasi
        $urutan = ['terima_bahan' => 1, 'pengolahan' => 2, 'pengemasan' => 3, 'pengantaran' => 4];
        $currentOrder = $urutan[$tahap];
        $prevSelesaiAt = null;

        if ($currentOrder > 1) {
            $prevTahapName = array_search($currentOrder - 1, $urutan, true);
            $prevRecorded = AlurOperasional::where('menu_id', $menu->id)
                ->where('batch_no', $batchNo)
                ->where('tahap', $prevTahapName)
                ->whereNotNull('waktu_selesai_aktual')
                ->first();

            if (!$prevRecorded) {
                session()->flash('warning', "Peringatan urutan tahap: Tahap sebelumnya ({$prevTahapName}) belum tercatat selesai.");
            } else {
                $prevSelesaiAt = Carbon::parse($prevRecorded->waktu_selesai_aktual);
            }
        }

        $selesaiAt = Carbon::parse($tanggal . ' ' . $now->format('H:i:s'));

        // Tentukan waktu mulai secara cerdas jika belum klik Mulai sebelumnya
        if ($existing && $existing->waktu_mulai_aktual) {
            $mulaiAt = Carbon::parse($existing->waktu_mulai_aktual);
        } elseif ($prevSelesaiAt && $prevSelesaiAt->lte($selesaiAt)) {
            $mulaiAt = $prevSelesaiAt->copy();
        } elseif ($jadwalTerkait && $jadwalTerkait->waktu_mulai_rencana) {
            $rencanaMulai = $this->parseStageDateTime($tanggal, substr((string) $jadwalTerkait->waktu_mulai_rencana, 0, 5));
            $mulaiAt = ($rencanaMulai && $rencanaMulai->lte($selesaiAt)) ? $rencanaMulai : $selesaiAt->copy();
        } else {
            $mulaiAt = $selesaiAt->copy();
        }

        if ($selesaiAt->lt($mulaiAt)) {
            $selesaiAt = $mulaiAt->copy();
        }

        $durasiMenit = (int) round($mulaiAt->diffInMinutes($selesaiAt));
        $isTerlambat = $this->isTahapTerlambat((int) $menu->id, $batchNo, $tanggal, $tahap, $selesaiAt);

        $payload = [
            'tanggal' => $tanggal,
            'waktu_mulai_aktual' => $mulaiAt,
            'waktu_selesai_aktual' => $selesaiAt,
            'petugas_aktual' => $existing?->petugas_aktual ?: $defaultPetugas,
            'porsi_terkemas' => $existing?->porsi_terkemas ?: $menu->jumlah_porsi_rencana,
            'status_tahap' => $isTerlambat ? 'terlambat' : 'selesai',
        ];

        // Jika tahap pengolahan selesai, otomatis catat waktu matang komponen & batas aman 4 jam
        if ($tahap === 'pengolahan') {
            $payload['waktu_matang_nasi'] = $existing?->waktu_matang_nasi ?: $selesaiAt;
            $payload['waktu_matang_lauk'] = $existing?->waktu_matang_lauk ?: $selesaiAt;
            $payload['waktu_matang_sayur'] = $existing?->waktu_matang_sayur ?: $selesaiAt;
            $payload['waktu_matang_terakhir'] = $selesaiAt;
        }

        DB::transaction(function () use ($menu, $batchNo, $tahap, $payload, $mulaiAt, $selesaiAt) {
            $record = AlurOperasional::updateOrCreate(
                [
                    'menu_id' => $menu->id,
                    'batch_no' => $batchNo,
                    'tahap' => $tahap,
                ],
                $payload
            );

            if ($tahap === 'pengantaran' && $record->pengantaranTitiks()->count() === 0) {
                foreach (KelompokPenerima::all() as $kel) {
                    $bDikirim = $kel->terkemas_porsi_besar ?: $kel->penerima_porsi_besar;
                    $kDikirim = $kel->terkemas_porsi_kecil ?: $kel->penerima_porsi_kecil;
                    $totDikirim = ($bDikirim + $kDikirim) > 0 ? ($bDikirim + $kDikirim) : ($kel->porsi_terkemas ?: $kel->jumlah_penerima);
                    PengantaranTitik::create([
                        'alur_operasional_id' => $record->id,
                        'kelompok_penerima_id' => $kel->id,
                        'petugas_antar' => $payload['petugas_aktual'],
                        'porsi_dikirim' => $totDikirim,
                        'porsi_besar_dikirim' => $bDikirim,
                        'porsi_kecil_dikirim' => $kDikirim,
                        'waktu_berangkat_aktual' => $mulaiAt,
                        'waktu_tiba_aktual' => $selesaiAt,
                        'status_antar' => 'Diterima',
                    ]);
                }
            }

            AuditLog::log('selesai_tahap_cepat', 'alur_operasional', (string) $record->id, null, $record->toArray());
        });

        if (in_array($tahap, ['pengemasan', 'pengantaran'], true)) {
            $this->predictionService->jalankanPrediksi($menu, $batchNo);
        }

        $infoDurasi = "{$mulaiAt->format('H.i')} – {$selesaiAt->format('H.i')} WITA ({$durasiMenit} menit)";
        $msg = "Tahap {$tahapLabel} selesai dicatat otomatis: {$infoDurasi}.";
        if ($tahap === 'pengolahan') {
            $msg .= ' Batas aman konsumsi 4 jam hingga ' . $selesaiAt->copy()->addHours(4)->format('H.i') . ' WITA.';
        }
        if ($isTerlambat) {
            $msg .= ' Status ditandai TERLAMBAT karena melewati target jadwal.';
        }

        return redirect()->route('admin.tahap.index', ['menu_id' => $menu->id, 'batch_no' => $batchNo])
            ->with('success', $msg);
    }

    public function updatePengantaranTitik(Request $request, PengantaranTitik $titik)
    {
        $validated = $request->validate([
            'petugas_antar' => 'nullable|string',
            'porsi_dikirim' => 'nullable|integer|min:0',
            'porsi_besar_dikirim' => 'nullable|integer|min:0',
            'porsi_kecil_dikirim' => 'nullable|integer|min:0',
            'waktu_berangkat_aktual' => 'nullable|string',
            'waktu_tiba_aktual' => 'nullable|string',
            'petugas_penerima' => 'nullable|string',
            'status_antar' => 'required|string',
        ]);

        if ($request->filled('porsi_besar_dikirim') || $request->filled('porsi_kecil_dikirim')) {
            $besar = (int) $request->input('porsi_besar_dikirim', 0);
            $kecil = (int) $request->input('porsi_kecil_dikirim', 0);
            $totalDikirim = $besar + $kecil;
        } else {
            $totalDikirim = (int) $request->input('porsi_dikirim', $titik->porsi_dikirim);
            $kecil = (int) round($totalDikirim * 0.35);
            $besar = max(0, $totalDikirim - $kecil);
        }

        $alur = $titik->alurOperasional;
        $tanggal = $alur?->tanggal ? Carbon::parse($alur->tanggal)->format('Y-m-d') : Carbon::today()->format('Y-m-d');

        $old = $titik->toArray();
        DB::transaction(function () use ($titik, $validated, $besar, $kecil, $totalDikirim, $tanggal, $request, $old) {
            $titik->update([
                'petugas_antar' => $validated['petugas_antar'] ?? $titik->petugas_antar,
                'porsi_dikirim' => $totalDikirim,
                'porsi_besar_dikirim' => $besar,
                'porsi_kecil_dikirim' => $kecil,
                'waktu_berangkat_aktual' => $this->parseStageDateTime($tanggal, $request->waktu_berangkat_aktual),
                'waktu_tiba_aktual' => $this->parseStageDateTime($tanggal, $request->waktu_tiba_aktual),
                'petugas_penerima' => $validated['petugas_penerima'] ?? null,
                'status_antar' => $validated['status_antar'],
            ]);

            AuditLog::log('update_pengantaran_titik', 'pengantaran_titik', (string)$titik->id, $old, $titik->fresh()->toArray());
        });

        return back()->with('success', 'Realisasi pengantaran titik (Porsi Besar & Kecil) berhasil diperbarui.');
    }

    // --- KPI MINGGUAN (EVALUASI 1X SEMINGGU) ---
    public function kpiMingguan(Request $request)
    {
        $daftarMinggu = $this->kpiService->daftarMingguEvaluasi();
        $weekStart = $request->input('week_start', Carbon::today()->startOfWeek(Carbon::MONDAY)->toDateString());
        $rekapMinggu = $this->kpiService->hitungRekapMingguan($weekStart, false);

        // Riwayat ringkas per minggu (maksimal 6 minggu)
        $riwayatMingguan = [];
        foreach (array_slice($daftarMinggu, 0, 6) as $wm) {
            $riwayatMingguan[] = $this->kpiService->hitungRekapMingguan($wm['week_start'], false);
        }

        return view('admin.kpi.index', compact(
            'daftarMinggu',
            'weekStart',
            'rekapMinggu',
            'riwayatMingguan'
        ));
    }

    public function hitungUlangKpi(Request $request)
    {
        $weekStart = $request->input('week_start', Carbon::today()->startOfWeek(Carbon::MONDAY)->toDateString());
        $rekap = $this->kpiService->hitungRekapMingguan($weekStart, true);

        if ($rekap['jumlah_batch'] === 0) {
            return redirect()->route('admin.kpi.index', ['week_start' => $rekap['week_start']])
                ->with('error', 'Belum ada batch yang menyelesaikan tahap pengemasan pada periode minggu ' . $rekap['label_minggu'] . '.');
        }

        AuditLog::log('rekap_kpi_mingguan', 'kpi_mingguan', $rekap['week_start'], null, [
            'week_start' => $rekap['week_start'],
            'jumlah_batch' => $rekap['jumlah_batch'],
            'skor_total' => $rekap['skor_total'],
            'kategori_kpi' => $rekap['kategori_kpi'],
        ]);

        return redirect()->route('admin.kpi.index', ['week_start' => $rekap['week_start']])
            ->with('success', 'Evaluasi KPI Mingguan (' . $rekap['label_minggu'] . ') berhasil direkap dari ' . $rekap['jumlah_batch'] . ' batch produksi.');
    }

    // --- PREDIKSI RISIKO RANDOM FOREST (TERPISAH DARI KPI) ---
    public function prediksiRisiko(Request $request)
    {
        $today = Carbon::today();
        $menus = Menu::orderByDesc('tanggal_penyajian')->get();
        $activeMenu = $request->filled('menu_id')
            ? Menu::with('menuBahans.bahanBaku')->find($request->menu_id)
            : (Menu::with('menuBahans.bahanBaku')->whereDate('tanggal_penyajian', $today)->first()
                ?? Menu::with('menuBahans.bahanBaku')->latest()->first());

        $batchNo = $request->input('batch_no', 'Tahap 1');
        $prediksi = null;
        $alurs = collect();
        $titikEvaluasi = [];
        $kelompoks = KelompokPenerima::all();

        if ($activeMenu) {
            $prediksi = KpiPrediksi::where('menu_id', $activeMenu->id)->where('batch_no', $batchNo)->first();
            $alurs = AlurOperasional::where('menu_id', $activeMenu->id)->where('batch_no', $batchNo)->get()->keyBy('tahap');
            $alurOlah = $alurs->get('pengolahan');
            $alurKemas = $alurs->get('pengemasan');
            $waktuMatang = $alurOlah?->waktu_matang_terakhir ?? $alurKemas?->waktu_matang_terakhir ?? $alurOlah?->waktu_selesai_aktual;

            foreach ($kelompoks as $kel) {
                $titikEvaluasi[] = [
                    'kelompok' => $kel,
                    'evaluasi' => $this->predictionService->evaluasiRisikoTitik(
                        $waktuMatang,
                        $alurKemas?->waktu_selesai_aktual,
                        (int) $kel->estimasi_waktu_tempuh_menit
                    ),
                ];
            }
        }

        $prediksiHistory = KpiPrediksi::with('menu')
            ->whereNotNull('kelas_prediksi')
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();
        $kurangData = !$alurs->has('pengemasan');

        return view('admin.prediksi.index', compact(
            'menus',
            'activeMenu',
            'batchNo',
            'prediksi',
            'alurs',
            'kelompoks',
            'titikEvaluasi',
            'prediksiHistory',
            'kurangData'
        ));
    }

    public function hitungUlangPrediksi(Request $request)
    {
        $request->validate([
            'menu_id' => 'required|exists:menus,id',
            'batch_no' => 'required|string',
        ]);

        $menu = Menu::with('menuBahans.bahanBaku')->findOrFail($request->menu_id);
        $hasil = $this->predictionService->jalankanPrediksi($menu, $request->batch_no);

        if (($hasil['status'] ?? '') === 'data_tidak_lengkap') {
            return redirect()->route('admin.prediksi.index', ['menu_id' => $menu->id, 'batch_no' => $request->batch_no])
                ->with('error', $hasil['message'] ?? 'Data tahap pengemasan atau waktu matang belum lengkap untuk menjalankan prediksi Random Forest.');
        }

        return redirect()->route('admin.prediksi.index', ['menu_id' => $menu->id, 'batch_no' => $request->batch_no])
            ->with('success', 'Prediksi kelayakan konsumsi Random Forest berhasil diperbarui.');
    }

    // --- HELPER PRIVAT ---
    private function handleFotoMenuUpload(Request $request): ?string
    {
        if (!$request->hasFile('foto_menu')) {
            return null;
        }
        $destDir = public_path('uploads/menus');
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $file = $request->file('foto_menu');
        $filename = 'menu-' . time() . '-' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move($destDir, $filename);
        return 'uploads/menus/' . $filename;
    }

    private function syncMenuBahans(Menu $menu, Request $request): void
    {
        if (!$request->has('bahan_ids')) {
            return;
        }
        $menu->menuBahans()->delete();
        $rasioKecil = ($menu->ukuran_porsi_besar_gram > 0)
            ? ($menu->ukuran_porsi_kecil_gram / $menu->ukuran_porsi_besar_gram)
            : 0.68;

        foreach ($request->bahan_ids as $index => $bahanId) {
            if ($bahanId) {
                $gramBesar = (float) ($request->berat_porsi_besar[$index] ?? $request->berat_per_sajian[$index] ?? 0);
                $gramKecil = isset($request->berat_porsi_kecil[$index]) && $request->berat_porsi_kecil[$index] !== ''
                    ? (float) $request->berat_porsi_kecil[$index]
                    : round($gramBesar * $rasioKecil, 1);

                $menu->menuBahans()->create([
                    'bahan_baku_id' => $bahanId,
                    'jumlah_batch' => $request->jumlah_batch[$index] ?? 1,
                    'satuan' => $request->satuan[$index] ?? 'kg',
                    'komponen' => $request->komponen[$index] ?? 'Lauk',
                    'berat_per_sajian_gram' => $gramBesar,
                    'berat_porsi_besar_gram' => $gramBesar,
                    'berat_porsi_kecil_gram' => $gramKecil,
                ]);
            }
        }
    }

    private function validateBahanRequest(Request $request, ?int $ignoreId = null): array
    {
        $uniqueRule = 'required|string|unique:bahan_bakus,kode_lot' . ($ignoreId ? ',' . $ignoreId : '');

        return $request->validate([
            'nama_bahan' => 'required|string',
            'kode_lot' => $uniqueRule,
            'kategori' => 'required|string',
            'stok_kg' => 'required|numeric|min:0.01',
            'satuan' => 'required|string',
            'tanggal_masuk' => 'required|date',
            'tanggal_kedaluwarsa' => 'required|date|after:tanggal_masuk',
            'supplier' => 'required|string',
        ], [
            'kode_lot.unique' => 'Kode lot sudah terdaftar.',
            'stok_kg.min' => 'Stok bahan minimal harus lebih dari 0 (FR-006).',
            'tanggal_kedaluwarsa.after' => 'Tanggal kedaluwarsa harus setelah tanggal masuk (FR-006).',
            'supplier.required' => 'Nama supplier wajib diisi.',
        ]);
    }

    private function findJadwalBentrok(string $tanggal, string $petugas, string $mulai, string $selesai, ?int $excludeId = null): ?JadwalProduksi
    {
        return JadwalProduksi::when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->whereDate('tanggal', $tanggal)
            ->where('petugas_rencana', $petugas)
            ->where('waktu_mulai_rencana', '<', $selesai)
            ->where('waktu_selesai_rencana', '>', $mulai)
            ->first();
    }

    private function isTahapTerlambat(int $menuId, string $batchNo, string $tanggal, string $tahap, Carbon $selesaiAt): bool
    {
        $jadwal = JadwalProduksi::where('menu_id', $menuId)
            ->where('batch_no', $batchNo)
            ->get()
            ->first(function (JadwalProduksi $item) use ($tahap) {
                $normalized = strtolower(str_replace([' ', '-'], '_', trim($item->tahap)));
                return $normalized === $tahap || str_contains($normalized, str_replace('_', ' ', $tahap));
            });

        $targetTime = $jadwal?->waktu_selesai_rencana ?? config("mbg.operasional.batas_tahap.{$tahap}");
        if (!$targetTime) {
            return false;
        }

        $toleransiMenit = (int) config('mbg.operasional.toleransi_terlambat_menit', 15);
        $deadline = Carbon::parse($tanggal . ' ' . $targetTime)->addMinutes($toleransiMenit);

        return $selesaiAt->gt($deadline);
    }

    private function parseStageDateTime(string $tanggal, ?string $value): ?Carbon
    {
        if (!$value || trim($value) === '') {
            return null;
        }
        $trimmed = trim($value);
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $trimmed)) {
            return Carbon::parse($tanggal . ' ' . $trimmed);
        }
        return Carbon::parse($trimmed);
    }

    // =========================================================================
    // RBAC — Manajemen Pengguna (FR-RBAC)
    // =========================================================================

    public function rbacIndex(Request $request)
    {
        $search     = $request->input('search');
        $roleFilter = $request->input('role');

        $query = User::query()->orderBy('name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($roleFilter) {
            $query->where('role', $roleFilter);
        }

        $users      = $query->paginate(15)->withQueryString();
        $totalAdmin = User::where('role', 'admin')->count();
        $totalGizi  = User::where('role', 'ahli_gizi')->count();
        $totalUsers = User::count();

        $auditLogs = AuditLog::with('user')
            ->whereIn('action', ['buat_pengguna', 'ubah_pengguna', 'hapus_pengguna', 'reset_password_pengguna', 'ubah_role'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('admin.rbac.index', compact(
            'users', 'totalAdmin', 'totalGizi', 'totalUsers', 'auditLogs', 'search', 'roleFilter'
        ));
    }

    public function rbacStore(Request $request)
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:100',
            'username'              => 'required|string|max:50|unique:users,username',
            'email'                 => 'required|email|unique:users,email',
            'role'                  => 'required|in:admin,ahli_gizi',
            'password'              => 'required|string|min:8|confirmed',
        ], [
            'username.unique'       => 'Username sudah digunakan.',
            'email.unique'          => 'Email sudah terdaftar.',
            'password.min'          => 'Password minimal 8 karakter.',
            'password.confirmed'    => 'Konfirmasi password tidak cocok.',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'username' => $data['username'],
            'email'    => $data['email'],
            'role'     => $data['role'],
            'password' => Hash::make($data['password']),
        ]);

        AuditLog::log(
            action: 'buat_pengguna',
            modelType: 'User',
            modelId: (string) $user->id,
            newValues: ['name' => $user->name, 'username' => $user->username, 'role' => $user->role]
        );

        return redirect()->route('admin.rbac.index')
            ->with('success', "Pengguna \"{$user->name}\" berhasil ditambahkan sebagai {$this->labelRole($user->role)}.");
    }

    public function rbacUpdate(Request $request, User $user)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'username' => 'required|string|max:50|unique:users,username,' . $user->id,
            'email'    => 'required|email|unique:users,email,' . $user->id,
            'role'     => 'required|in:admin,ahli_gizi',
        ], [
            'username.unique' => 'Username sudah digunakan.',
            'email.unique'    => 'Email sudah terdaftar.',
        ]);

        if ($user->id === auth()->id() && $data['role'] !== $user->role) {
            return redirect()->route('admin.rbac.index')
                ->with('error', 'Anda tidak dapat mengubah role akun Anda sendiri.');
        }

        $old = ['name' => $user->name, 'username' => $user->username, 'email' => $user->email, 'role' => $user->role];
        $user->update($data);

        AuditLog::log(
            action: $old['role'] !== $user->role ? 'ubah_role' : 'ubah_pengguna',
            modelType: 'User',
            modelId: (string) $user->id,
            oldValues: $old,
            newValues: ['name' => $user->name, 'username' => $user->username, 'email' => $user->email, 'role' => $user->role]
        );

        return redirect()->route('admin.rbac.index')
            ->with('success', "Data pengguna \"{$user->name}\" berhasil diperbarui.");
    }

    public function rbacDestroy(Request $request, User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.rbac.index')
                ->with('error', 'Anda tidak dapat menghapus akun yang sedang digunakan.');
        }

        $nama = $user->name;
        AuditLog::log(
            action: 'hapus_pengguna',
            modelType: 'User',
            modelId: (string) $user->id,
            oldValues: ['name' => $user->name, 'username' => $user->username, 'role' => $user->role]
        );

        $user->delete();

        return redirect()->route('admin.rbac.index')
            ->with('success', "Pengguna \"{$nama}\" berhasil dihapus dari sistem.");
    }

    public function rbacResetPassword(Request $request, User $user)
    {
        $data = $request->validate([
            'new_password' => 'required|string|min:8|confirmed',
        ], [
            'new_password.min'       => 'Password baru minimal 8 karakter.',
            'new_password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $user->update(['password' => Hash::make($data['new_password'])]);

        AuditLog::log(
            action: 'reset_password_pengguna',
            modelType: 'User',
            modelId: (string) $user->id,
            newValues: ['username' => $user->username, 'reset_by' => auth()->user()->username]
        );

        return redirect()->route('admin.rbac.index')
            ->with('success', "Password pengguna \"{$user->name}\" berhasil direset.");
    }

    private function labelRole(string $role): string
    {
        return match ($role) {
            'admin'     => 'Admin SPPG',
            'ahli_gizi' => 'Ahli Gizi',
            default     => $role,
        };
    }
}
