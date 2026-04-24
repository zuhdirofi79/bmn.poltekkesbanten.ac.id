<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\BarangInventaris;
use App\BarangBHP_alat;
use App\BarangBHP_bahan;
use App\Peminjaman;
use App\PeminjamanDetail;
use App\KunjunganLab;
use App\Ruangan;
use App\Aktifitas;
use App\Semester;
use App\Tujuan;
use App\Kelas;
use App\ReqJadwalPraktikum;
use DB;
use Intervention\Image\Facades\Image;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    public function jurusan()
    {
      return auth()->user()->jurusan;
    }

    public function daftar_barang(Request $request)
    {
        $jurusan = $request->jurusan;
        $barang = BarangInventaris::where('jurusan','=',$jurusan)->whereNull('delete')->OrderBy('kode_barang')->get();
        return view('user.barang',compact('barang','jurusan'));
    }

    public function daftar_barangbhp(Request $request)
    {
        $jurusan = $request->jurusan;
        $bhp_bahan = BarangBHP_bahan::where('jurusan','=',$jurusan)->aktif()->OrderBy('nama')->get();
        return view('user.barangbhp',compact('bhp_bahan','jurusan'));
    }

    public function daftar_barangmedis(Request $request)
    {
        $jurusan = $request->jurusan;
        $bhp_alat = BarangBHP_alat::where('jurusan','=',$jurusan)->whereNull('delete')->OrderBy('nama')->get();
        return view('user.barangmedis',compact('bhp_alat','jurusan'));
    }

    public function peminjaman()
    {
        $jurusan = $this->jurusan();
        $user_id = Auth()->user()->id;
        $data = Peminjaman::where('user_id','=',$user_id)->orderBy('id','desc')->get();
        $ruangan = Ruangan::where('jurusan','=',$jurusan)->get();
        return view('user.peminjaman',compact('jurusan','ruangan','data'));
    }

    public function peminjaman_detail($id)
    {
        $cek = Peminjaman::find($id);
        $jenis = $cek->jenis;

        if($cek->jenis=='Aset Laboratorium'){
            $sql = "select b.jenis, c.satuan, c.nama, a.stok_pinjam, b.tgl_pinjaman, b.tgl_pengembalian, a.status
                    from peminjaman_detail a
                    join peminjaman b on (a.peminjaman_id=b.id)
                    join barang_inventaris c on (a.barang_id=c.id)
                    where a.peminjaman_id=$id";
        }elseif($cek->jenis=='Alat Medis'){
            $sql = "select b.jenis, c.satuan, c.nama, a.stok_pinjam, b.tgl_pinjaman, b.tgl_pengembalian, a.status
                    from peminjaman_detail a
                    join peminjaman b on (a.peminjaman_id=b.id)
                    join barangbhp_alat c on (a.barang_id=c.id)
                    where a.peminjaman_id=$id";
        }else{
            $sql = "select b.jenis, c.satuan, c.nama, a.stok_pinjam, b.tgl_pinjaman, b.tgl_pengembalian, a.status
                    from peminjaman_detail a
                    join peminjaman b on (a.peminjaman_id=b.id)
                    join barangbhp_bahan c on (a.barang_id=c.id)
                    where a.peminjaman_id=$id";
        }

        $data = DB::select($sql);
        return view('user.peminjaman_detail',compact('data','jenis'));
    }

    public function req_peminjaman(Request $request)
    {
        $jurusan = $this->jurusan();
        $ruangan = $request->ruang;
        $jenis = $request->jenis;
        $tujuan = Tujuan::get();
        $tingkat = DB::select("select * from tingkat");
        $prodis = \App\Models\Prodi::orderBy('nama_prodi','asc')->get();
        if($jenis=='Aset Laboratorium'){
            $barang = BarangInventaris::where('jurusan','=',$jurusan)->where('ruang','=',$ruangan)->whereNull('delete')->OrderBy('kode_barang')->get();
        }elseif($jenis=='Alat Medis'){
            $barang = BarangBHP_alat::where('jurusan','=',$jurusan)->whereNull('delete')->OrderBy('nama')->get();
        }else{
            $barang = BarangBHP_bahan::where('jurusan','=',$jurusan)->aktif()->OrderBy('nama')->get();
        }
        return view('user.req_peminjaman',compact('jurusan','ruangan','jenis','barang','tingkat','tujuan','prodis'));
    }

    public function add_peminjaman(Request $request)
    {
        $barang_ids = collect($request->barang_id)->filter();

        $user_id = Auth()->user()->id;
        $validatedData = $request->validate([
            'jenis' => ['required'],
            'jurusan' => ['required'],
            'ruangan' => ['required'],
            'tingkat' => ['required'],
            'prodi_id' => ['required','exists:prodi,id'],
            'tgl_peminjaman' => ['required'],
            'waktu_mulai' => ['required'],
            'waktu_selesai' => ['required'],
            'tujuan' => ['required'],
            'barang_id' => ['required','array'],
            'bukti_foto' => ['nullable','array','max:5'],
            'bukti_foto.*' => ['image','mimes:jpeg,jpg,png','max:2048'],
        ]);

        // Validasi konflik stok per barang berdasarkan waktu peminjaman
        foreach ($barang_ids as $barangId) {
            if ($request->jenis == 'Aset Laboratorium') {
                $barang = BarangInventaris::find($barangId);
            } elseif ($request->jenis == 'Alat Medis') {
                $barang = BarangBHP_alat::find($barangId);
            } else {
                $barang = BarangBHP_bahan::where('id', $barangId)->aktif()->first();
            }

            $stokTersedia = $barang ? $barang->stok : 0;
            if ($request->jenis !== 'Aset Laboratorium' && $request->jenis !== 'Alat Medis' && ! $barang) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Salah satu barang bahan medis tidak ditemukan atau sudah tidak aktif.');
            }

            // Total yang sudah dipinjam pada waktu yang overlap (status detail bukan Ditolak)
            $dipinjam = PeminjamanDetail::where('barang_id', $barangId)
                ->where('status', '!=', 'Ditolak')
                ->whereHas('peminjaman', function ($q) use ($request) {
                    $q->where('tgl_pinjaman', $request->tgl_peminjaman)
                      ->whereIn('status', ['Menunggu Konfirmasi', 'Menunggu Dikembalikan'])
                      ->where(function ($query) use ($request) {
                          $query->where('waktu_mulai', '<', $request->waktu_selesai)
                                ->where('waktu_selesai', '>', $request->waktu_mulai);
                      });
                })
                ->sum('stok_pinjam');

            // Saat ini setiap checkbox = 1 unit
            $kebutuhanBaru = 1;

            if ($dipinjam + $kebutuhanBaru > $stokTersedia) {
                $namaBarang = $barang ? $barang->nama : 'Barang';
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Stok ' . $namaBarang . ' tidak tersedia pada jam tersebut.');
            }
        }
        
        if($request->tingkat=='Other'){
            $tingkat = $request->tingkat_other;
        } else {
            $tingkat = $request->tingkat;
        }
        
        $peminjaman_id = Peminjaman::insertGetId([
            'user_id' => $user_id,
            'jurusan' => $request->jurusan,
            'jenis' => $request->jenis,
            'ruangan' => $request->ruangan,
            'tingkat' => $tingkat,
            'prodi_id' => $request->prodi_id,
            'waktu_mulai' => $request->waktu_mulai,
            'waktu_selesai' => $request->waktu_selesai,
            'tujuan' => $request->tujuan,
            'tgl_pinjaman' => $request->tgl_peminjaman,
            'tgl_pengembalian' => $request->tgl_pengembalian,
            'status' => 'Menunggu Konfirmasi',
        ]);

        if ($request->hasFile('bukti_foto')) {

            $uploadDir = '/home/u708283844/domains/laboratorium.poltekkesbanten.ac.id/public_html/storage/bukti_peminjaman';
        
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
        
            foreach ($request->file('bukti_foto') as $file) {
        
                $filename = 'PMJ-'.$peminjaman_id.'-'.time().'.jpg';
                $fullPath = $uploadDir . '/' . $filename;
        
                Image::make($file)
                    ->orientate()
                    ->resize(1600, null, function ($constraint) {
                        $constraint->aspectRatio();
                    })
                    ->encode('jpg', 70)
                    ->save($fullPath);
        
                DB::table('peminjaman_foto')->insert([
                    'peminjaman_id' => $peminjaman_id,
                    'foto_path' => 'storage/bukti_peminjaman/' . $filename,
                    'created_at' => now(),
                ]);
            }
        }

        foreach($barang_ids as $barang){
            PeminjamanDetail::insert([
                'peminjaman_id'=>$peminjaman_id,
                'barang_id'=>$barang,
                'stok_pinjam'=>1,
                'status'=>'Menunggu Konfirmasi',
            ]);
        }

        return redirect('user/peminjaman')->with('message', 'Permintaan Peminjaman peralatan berhasil diajukan.');


    }

    public function add_request(Request $request)
    {
        $validatedData = $request->validate([
            'username' => ['required'],
            'kode_barang' => ['required'],
            'stok_pinjaman' => ['required'],
            'keperluan' => ['required'],
        ]);
        $pinjaman = Pinjaman::insert([
            'username' => $request->username,
            'kode_barang' => $request->kode_barang,
            'keperluan' => $request->keperluan,
            'stok_pinjaman' => $request->stok_pinjaman,
            'status' => 'Menunggu Konfirmasi',
        ]);

        return redirect('user/history')->with('message', 'Data request berhasil ditambahkan.');
    }

    public function history()
    {
        $username = Auth()->user()->username;
        $sql = "select a.*, b.kode_barang, b.nama_barang, b.kategori
                from pinjaman_pengembalian a
                join barang b on (a.kode_barang=b.kode_barang)
                where a.username='$username' order by a.tgl_request desc";
        $data = DB::select($sql);
        return view('user.history',compact('data'));
    }

    public function kunjungan()
    {
        $jurusan = $this->jurusan();
        $ruangan = Ruangan::where('jurusan','=',$jurusan)->get();
        $user_id = auth()->user()->id;
        $tujuan = Tujuan::get();

        $sql = "SELECT a.*, b.username, b.name
                FROM kunjungan_lab a
                join users b on (a.user_id=b.id)
                where a.user_id=$user_id order by a.id desc";
        $data = DB::select($sql);
        return view('user.kunjungan', compact('jurusan','ruangan','data','tujuan'));
    }

    public function add_kunjungan(Request $request)
    {
        $validatedData = $request->validate([
            'tgl_kunjungan' => ['required'],
            'waktu_mulai' => ['required'],
            'waktu_selesai' => ['required'],
            'ruang' => ['required'],
            'kelas' => ['required'],
            'tujuan' => ['required'],
        ]);

        KunjunganLab::insert([
            'user_id' => auth()->user()->id,
            'jurusan' => auth()->user()->jurusan,
            'prodi' => $request->prodi,
            'tgl_kunjungan' => $request->tgl_kunjungan,
            'waktu_mulai' => $request->waktu_mulai,
            'waktu_selesai' => $request->waktu_selesai,
            'ruang_lab' => $request->ruang,
            'kelas' => $request->kelas,
            'tujuan' => $request->tujuan,
            'keterangan' => $request->keterangan,
        ]);

        Aktifitas::insert([
            'username' => auth()->user()->username,
            'name' => auth()->user()->name,
            'aktifitas' => 'Kunjungan Laboratorium',
        ]);

        return redirect()->back()->with('message', 'Form Kunjungan berhasil ditambahkan.');
    }

    public function jadwal_praktikum()
    {
        $jurusan = $this->jurusan();
        $semester = Semester::where('status','=','aktif')->first();

        $sql = "select a.*
                from jadwal_praktikum a
                join hari b on (a.hari=b.hari)
                where a.jurusan='$jurusan' and a.semester_id=$semester->id
                order by a.tgl, a.ruang, a.waktu_mulai";
        $data = DB::select($sql);

        $user_id = Auth()->user()->id;

        $sql2 = "select a.*
                from req_jadwal_praktikum a
                join hari b on (a.hari=b.hari)
                where user_id=$user_id and a.jurusan='$jurusan' and a.semester_id=$semester->id
                order by b.id, a.ruang, a.waktu_mulai";
        $data_req = DB::select($sql2);

        // dd($user_id);


        return view('user.jadwal_praktikum',compact('jurusan','semester','data','data_req'));
    }

    public function req_jadwal()
    {
        $jurusan = $this->jurusan();
        $ruangan = Ruangan::where('jurusan','=',$jurusan)->get();
        $hari = DB::select("select * from hari");
        $tingkat = DB::select("select * from tingkat");
        $kelas = Kelas::where('jurusan','=',$jurusan)->get();
        $tujuan = Tujuan::get();
        return view('user.req_jadwal',compact('jurusan','ruangan','hari','tingkat','kelas','tujuan'));
    }

    public function add_jadwal(Request $request)
    {
        $validatedData = $request->validate([
            'tgl' => ['required'],
            'penanggung_jawab' => ['required'],
            'kelas' => ['required'],
            'tingkat' => ['required'],
            'ruang' => ['required'],
            'hari' => ['required'],
            'waktu_mulai' => ['required'],
            'waktu_selesai' => ['required'],
            'tujuan' => ['required'],
        ]);
        
        if($request->kelas=='Other'){
            $kelas = $request->kelas_other;
        } else {
            $kelas = $request->kelas;
        }

        if($request->tingkat=='Other'){
            $tingkat = $request->tingkat_other;
        } else {
            $tingkat = $request->tingkat;
        }
        
        $semester = Semester::where('status','=','aktif')->first();
        ReqJadwalPraktikum::insert([
            'user_id' => Auth()->user()->id,
            'semester_id' => $semester->id,
            'tgl' => $request->tgl,
            'jurusan' => $request->jurusan,
            'penanggung_jawab' => $request->penanggung_jawab,
            'kelas' => $kelas,
            'tingkat' => $tingkat,
            'ruang' => $request->ruang,
            'hari' => $request->hari,
            'waktu_mulai' => $request->waktu_mulai,
            'waktu_selesai' => $request->waktu_selesai,
            'tujuan' => $request->tujuan,
            'status' => 'Menunggu Konfirmasi',
            'updated_at' => Auth()->user()->name
        ]);

        return redirect('user/jadwal_praktikum')->with('message', 'Data jadwal praktek berhasil diajukan, Silahkan hubungi petugas untuk konfirmasi lebih lanjut.');
    }

}