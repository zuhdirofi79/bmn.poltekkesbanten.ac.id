<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\BarangInventaris;
use App\BarangBHP_alat;
use App\BarangBHP_bahan;
use App\BarangMasuk;
use App\Aktifitas;
use App\Ruangan;
use App\Semester;
use App\Kelas;
use App\JadwalPraktikum;
use App\ReqJadwalPraktikum;
use App\Peminjaman;
use App\PeminjamanDetail;
use App\PenambahanBarang;
use App\Tujuan;
use App\User;
use DB;
use PDF;
use App\Notifications\EmailNotification;

class PetugasLabController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // INVENTARIS LAB _________________________________________________

    public function barang(Request $request)
    {
        $jurusan = $request->jurusan;
        $ruangan = Ruangan::where('jurusan','=',$jurusan)
            ->orderBy('kampus')->orderBy('nama_ruang')->get();
        $barang = BarangInventaris::with(['ruangan.location'])
            ->where('jurusan','=',$jurusan)
            ->whereNull('delete')
            ->OrderBy('nama','asc')
            ->OrderBy('ruang','asc')
            ->get();
        return view('petugas_lab.barang',compact('barang','jurusan','ruangan'));
    }

    public function add_barang(Request $request)
    {
        $validatedData = $request->validate([
            'nama_barang' => ['required'],
            'tgl_masuk' => ['required'],
            'merk' => ['required'],
            'type' => ['required'],
            'no_seri' => ['required'],
            'kode_barang' => ['required'],
            'ruangan_id' => ['required'],
            'stok' => ['required'],
            'kondisi' => ['required'],
        ]);

        $user = Auth()->user()->name;
        $username = Auth()->user()->username;

        $ruanganRecord = Ruangan::find($request->ruangan_id);
        
        $idBaru = BarangInventaris::insertGetId([
            'jurusan' => $request->jurusan,
            'nama' => $request->nama_barang,
            'tgl_masuk' => $request->tgl_masuk,
            'merk' => $request->merk,
            'type' => $request->type,
            'no_seri' => $request->no_seri,
            'kode_barang' => $request->kode_barang,
            'ruang' => $ruanganRecord ? $ruanganRecord->nama_ruang : null,
            'ruangan_id' => $request->ruangan_id,
            'stok' => $request->stok,
            'satuan'=> 'Unit',
            'kondisi'=> $request->kondisi,
            'updated_at' => $user
        ]);
        
        PenambahanBarang::insert([
            'jenis_barang' => 'Aset Laboratorium',
            'barang_id' => $idBaru,
            'stok' => $request->stok,
            'keterangan' => 'Penambahan Data baru',
            'petugas' => $user,
        ]);

        Aktifitas::insert([
            'username' => $username,
            'name' => $user,
            'aktifitas' => 'Penambahan Aset Laboratorium',
        ]);

        return redirect()->back()->with('message', 'Data barang berhasil ditambahkan.'); 
    }

    public function edit_barang(Request $request)
    {
        $validatedData = $request->validate([
            'nama_barang' => ['required'],
            'tgl_masuk' => ['required'],
            'merk' => ['required'],
            'type' => ['required'],
            'no_seri' => ['required'],
            'kode_barang' => ['required'],
            'ruangan_id' => ['required'],
            'kondisi' => ['required']
        ]);

        $user = Auth()->user()->name;

        $ruanganRecord = Ruangan::find($request->ruangan_id);

        BarangInventaris::find($request->id)->update([
            'nama' => $request->nama_barang,
            'tgl_masuk' => $request->tgl_masuk,
            'merk' => $request->merk,
            'type' => $request->type,
            'no_seri' => $request->no_seri,
            'kode_barang' => $request->kode_barang,
            'ruang' => $ruanganRecord ? $ruanganRecord->nama_ruang : $request->ruang,
            'ruangan_id' => $request->ruangan_id ?: null,
            'kondisi' => $request->kondisi,
            'updated_at' => $user
        ]);

        return redirect()->back()->with('message', 'Data barang berhasil dirubah.');
    }

    public function delete_barang(Request $request)
    {
        $user = Auth()->user()->name;
        BarangInventaris::find($request->id)->update([
            'updated_at' => $user,
            'delete' => $request->delete
        ]);
        return redirect()->back()->with('message', 'Data barang berhasil dihapus.');
    }

    // BARANG BAHAN MEDIS _________________________________________________

    public function barangbhp(Request $request)
    {
        $jurusan = $request->jurusan;
        $ruangan = Ruangan::where('jurusan','=',$jurusan)
            ->orderBy('kampus')->orderBy('nama_ruang')->get();
        $bhp_bahan = BarangBHP_bahan::with(['ruangan.location'])
            ->where('jurusan','=',$jurusan)->whereNull('delete')->OrderBy('nama')->get();
        return view('petugas_lab.barangbhp',compact('bhp_bahan','jurusan','ruangan'));
    }

    public function add_bhp_bahan(Request $request)
    {
        $validatedData = $request->validate([
            'jurusan' => ['required'],
            'nama' => ['required'],
            'kode_barang' => ['required'],
            'stok' => ['required'],
            'satuan' => ['required'],
            'tgl_expired' => ['required'],
        ]);
        $user = Auth()->user()->name;
        $username = Auth()->user()->username;
        
        $idBaru = BarangBHP_bahan::insertGetId([
            'jurusan' => $request->jurusan,
            'nama' => $request->nama,
            'kode_barang' => $request->kode_barang,
            'kimia' => $request->kimia,
            'stok' => $request->stok,
            'satuan' => $request->satuan,
            'tgl_expired' => $request->tgl_expired,
            'ruangan_id' => $request->ruangan_id ?: null,
            'updated_at' => $user
        ]);
        
        PenambahanBarang::insert([
            'jenis_barang' => 'Bahan Medis',
            'barang_id' => $idBaru, 
            'stok' => $request->stok,
            'keterangan' => 'Penambahan Data baru',
            'petugas' => $user,
            'tgl_expired' => $request->tgl_expired,
        ]);
        
        Aktifitas::insert([
            'username' => $username,
            'name' => $user,
            'aktifitas' => 'Penambahan Bahan Medis',
        ]);
        
        return redirect()->back()->with('message', 'Data BHP Bahan berhasil ditambah.');
    }
    
    public function edit_bhp($id)
    {
        $data = BarangBHP_bahan::with('ruangan.location')->find($id);
        $ruangan = Ruangan::where('jurusan','=',$data->jurusan)
            ->orderBy('kampus')->orderBy('nama_ruang')->get();
        return view('petugas_lab.edit_bhp',compact('data','ruangan'));
    }

    public function edit_bhp_bahan(Request $request)
    {
        $validatedData = $request->validate([
            'nama' => ['required'],
            'kode_barang' => ['required'],
            'satuan' => ['required'],
            'tgl_expired' => ['required'],
        ]);
        $user = Auth()->user()->name;
        BarangBHP_bahan::find($request->id)->update([
            'nama' => $request->nama,
            'kode_barang' => $request->kode_barang,
            'satuan' => $request->satuan,
            'tgl_expired' => $request->tgl_expired,
            'ruangan_id' => $request->ruangan_id ?: null,
            'updated_at' => $user
        ]);
        return redirect('petugas/barangbhp?jurusan='.$request->jurusan)->with('message', 'Data BHP bahan berhasil dirubah.');
        //return redirect()->back()->with('message', 'Data BHP bahan berhasil dirubah.');
    }

    public function xxdelete_bhp_bahan(Request $request)
    {
        $user = Auth()->user()->name;
        BarangBHP_bahan::find($request->id)->update([
            'updated_at' => $user,
            'delete' => $request->delete
        ]);
        return redirect()->back()->with('message', 'Data BHP bahan berhasil dihapus.');
    }
    
    public function delete_bhp_bahan(Request $request)
    {
        $user = Auth()->user()->name;
        $ids = $request->input('ids'); // array of selected IDs

        if (!$ids || count($ids) === 0) {
            return redirect()->back()->with('error', 'Tidak ada data yang dipilih untuk dihapus.');
        }

        foreach ($ids as $id) {
            BarangBHP_bahan::where('id', $id)->update([
                'updated_at' => $user,
                'delete' => 'hapus data'
            ]);
        }

        return redirect()->back()->with('message', 'Semua data yang ditandai berhasil dihapus.');
    }

// BARANG ALAT MEDIS ______________________________________________________________________________________________________

    public function barangmedis(Request $request)
    {
        $jurusan = $request->jurusan;
        $ruangan = Ruangan::where('jurusan','=',$jurusan)
            ->orderBy('kampus')->orderBy('nama_ruang')->get();
        $bhp_alat = BarangBHP_alat::with(['ruangan.location'])
            ->where('jurusan','=',$jurusan)->whereNull('delete')->OrderBy('nama')->get();
        return view('petugas_lab.barangmedis',compact('bhp_alat','jurusan','ruangan'));
    }

    public function add_bhp_alat(Request $request)
    {
        $validatedData = $request->validate([
            'jurusan' => ['required'],
            'nama' => ['required'],
            'stok' => ['required'],
            'satuan' => ['required'],
            'kondisi' => ['required'],
        ]);
        $user = Auth()->user()->name;
        $username = Auth()->user()->username;
        
        $idBaru = BarangBHP_alat::insertGetId([
            'jurusan' => $request->jurusan,
            'nama' => $request->nama,
            'stok' => $request->stok,
            'satuan' => $request->satuan,
            'kondisi' => $request->kondisi,
            'ruangan_id' => $request->ruangan_id ?: null,
            'updated_at' => $user
        ]);
        
        PenambahanBarang::insert([
            'jenis_barang' => 'Alat Medis',
            'barang_id' => $idBaru,
            'stok' => $request->stok,
            'keterangan' => 'Penambahan Data baru',
            'petugas' => $user,
        ]);
        
        Aktifitas::insert([
            'username' => $username,
            'name' => $user,
            'aktifitas' => 'Penambahan Alat Medis',
        ]);
        
        return redirect()->back()->with('message', 'Data BHP Alat berhasil ditambah.');
    }

    public function edit_bhp_alat(Request $request)
    {
        $validatedData = $request->validate([
            'nama' => ['required'],
            'satuan' => ['required'],
            'kondisi' => ['required'],
        ]);
        $user = Auth()->user()->name;
        BarangBHP_alat::find($request->id)->update([
            'nama' => $request->nama,
            'satuan' => $request->satuan,
            'kondisi' => $request->kondisi,
            'ruangan_id' => $request->ruangan_id ?: null,
            'updated_at' => $user
        ]);
        return redirect()->back()->with('message', 'Data BHP alat berhasil dirubah.');
    }

    public function delete_bhp_alat(Request $request)
    {
        $user = Auth()->user()->name;
        BarangBHP_alat::find($request->id)->update([
            'updated_at' => $user,
            'delete' => $request->delete
        ]);
        return redirect()->back()->with('message', 'Data BHP alat berhasil dihapus.');
    }

    // RUANG _________________________________________________

    public function ruang(Request $request)
    {
        $jurusan = $request->jurusan;
        $semester = Semester::where('status','=','aktif')->first();

        $sql = "select a.*
                from jadwal_praktikum a
                join hari b on (a.hari=b.hari)
                where a.jurusan='$jurusan' and a.semester_id=$semester->id
                order by a.tgl, a.ruang, a.waktu_mulai";
        $data = DB::select($sql);
        return view('petugas_lab.ruang',compact('jurusan','semester','data'));
    }

    public function tambah_jadwal(Request $request)
    {
        $jurusan = $request->jurusan;
        $ruangan = Ruangan::where('jurusan','=',$jurusan)->orderBy('kampus', 'asc')->orderBy('nama_ruang','asc')->get();
        $hari = DB::select("select * from hari");
        $tingkat = DB::select("select * from tingkat order by tingkat");
        $kelas = Kelas::where('jurusan','=',$jurusan)->orderBy('matkul','asc')->get();
        $tujuan = Tujuan::get();
        return view('petugas_lab.add_jadwal',compact('jurusan','ruangan','hari','tingkat','kelas','tujuan'));
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
        $user = Auth()->user()->name;
        JadwalPraktikum::insert([
            'semester_id' => $semester->id,
            'jurusan' => $request->jurusan,
            'tgl' => $request->tgl,
            'penanggung_jawab' => $request->penanggung_jawab,
            'kelas' => $kelas,
            'tingkat' => $tingkat,
            'ruang' => $request->ruang,
            'hari' => $request->hari,
            'waktu_mulai' => $request->waktu_mulai,
            'waktu_selesai' => $request->waktu_selesai,
            'tujuan' => $request->tujuan,
            'updated_at' => $user
        ]);

        return redirect('petugas/ruang?jurusan='.$request->jurusan)->with('message', 'Data jadwal praktek berhasil ditambah.');
    }

    public function detail_jadwal($id)
    {
        $data = JadwalPraktikum::find($id);
        $jurusan = $data->jurusan;
        $ruangan = Ruangan::where('jurusan','=',$jurusan)->get();
        $hari = DB::select("select * from hari");
        $tingkat = DB::select("select * from tingkat");
        $kelas = Kelas::where('jurusan','=',$jurusan)->orderBy('matkul','asc')->get();
        $tujuan = Tujuan::get();
        return view('petugas_lab.detail_jadwal',compact('jurusan','ruangan','hari','tingkat','kelas','data','tujuan'));
    }

    public function edit_jadwal(Request $request)
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
        
        $user = Auth()->user()->name;
        JadwalPraktikum::find($request->id)->update([
            'jurusan' => $request->jurusan,
            'tgl' => $request->tgl,
            'penanggung_jawab' => $request->penanggung_jawab,
            'kelas' => $kelas,
            'tingkat' => $tingkat,
            'ruang' => $request->ruang,
            'hari' => $request->hari,
            'waktu_mulai' => $request->waktu_mulai,
            'waktu_selesai' => $request->waktu_selesai,
            'tujuan' => $request->tujuan,
            'updated_at' => $user
        ]);

        return redirect('petugas/ruang?jurusan='.$request->jurusan)->with('message', 'Data jadwal praktek berhasil dirubah.');
    }

    public function delete_jadwal(Request $request)
    {
        JadwalPraktikum::find($request->id)->delete();
        return redirect()->back()->with('message', 'Data jadwal praktek berhasil dihapus.');
    }

    

    // REQUEST JADWAL _________________________________________________

    public function request_jadwal(Request $request)
    {
        $jurusan = $request->jurusan;
        $semester = Semester::where('status','=','aktif')->first();

        $sql = "select a.*
                from jadwal_praktikum a
                join hari b on (a.hari=b.hari)
                where a.jurusan='$jurusan' and a.semester_id=$semester->id
                order by b.id, a.ruang, a.waktu_mulai";
        $data = DB::select($sql);

        $data = ReqJadwalPraktikum::where('status','=','Menunggu Konfirmasi')->get();
        return view('petugas_lab.request_jadwal',compact('jurusan','semester','data'));
    }

    public function submit_request_jadwal(Request $request)
    {
        $validatedData = $request->validate([
            'status' => ['required'],
        ]);
        $user = Auth()->user()->name;
        $req = ReqJadwalPraktikum::find($request->id);
        $req->update([
            'status' => $request->status,
            'keterangan' => $request->keterangan,
            'petugas' => $user
        ]);

        if($request->status=='Diterima'){
            JadwalPraktikum::insert([
                'semester_id' => $req->semester_id,
                'tgl' => $req->tgl,
                'jurusan' => $req->jurusan,
                'penanggung_jawab' => $req->penanggung_jawab,
                'kelas' => $req->kelas,
                'tingkat' => $req->tingkat,
                'ruang' => $req->ruang,
                'hari' => $req->hari,
                'waktu_mulai' => $req->waktu_mulai,
                'waktu_selesai' => $req->waktu_selesai,
                'tujuan' => $req->tujuan,
                'updated_at' => $user
            ]);
        }

        return redirect()->back()->with('message', 'Data jadwal praktek berhasil diupdate.');
    }



    // REQUEST _________________________________________________

    public function request(Request $request)
    {
        $jurusan = $request->jurusan;
        $sql = "select a.*, b.name
                from peminjaman a 
                join users b on (a.user_id=b.id)
                where a.jurusan='$jurusan' and a.status='Menunggu Konfirmasi'";
        $data = DB::select($sql);
        
        //$data = Peminjaman::where('jurusan','=',$jurusan)->where('status','=','Menunggu Konfirmasi')->orderBy('id','desc')->get();
        return view('petugas_lab.request',compact('data','jurusan'));
    }

    public function request_detail($id)
    {
        $cek = Peminjaman::find($id);
        $jenis = $cek->jenis;
        $jurusan = $cek->jurusan;
        if($cek->jenis=='Aset Laboratorium'){
            $sql = "select a.id, b.jenis, a.barang_id, c.nama, c.stok, c.satuan, a.stok_pinjam, b.tgl_pinjaman, b.tgl_pengembalian, a.status
                    from peminjaman_detail a
                    join peminjaman b on (a.peminjaman_id=b.id)
                    join barang_inventaris c on (a.barang_id=c.id)
                    where a.peminjaman_id=$id";
        }elseif($cek->jenis=='Alat Medis'){
            $sql = "select a.id, b.jenis, a.barang_id, c.nama, c.stok, c.satuan, a.stok_pinjam, b.tgl_pinjaman, b.tgl_pengembalian, a.status
                    from peminjaman_detail a
                    join peminjaman b on (a.peminjaman_id=b.id)
                    join barangbhp_alat c on (a.barang_id=c.id)
                    where a.peminjaman_id=$id";
        }else{
            $sql = "select a.id, b.jenis, a.barang_id, c.nama,c.stok, c.satuan, a.stok_pinjam, b.tgl_pinjaman, b.tgl_pengembalian, a.status
                    from peminjaman_detail a
                    join peminjaman b on (a.peminjaman_id=b.id)
                    join barangbhp_bahan c on (a.barang_id=c.id)
                    where a.peminjaman_id=$id";
        }
        $data = DB::select($sql);
        return view('petugas_lab.request_detail',compact('data','jurusan','id','jenis'));
    }

    public function request_submit(Request $request)
    {
        $cek = Peminjaman::find($request->peminjaman_id);
        $jurusan = $cek->jurusan;
        $user = auth()->user()->name;
        $peminjam = User::find($cek->user_id);

        $jumlah = count($request->id);
        if($cek->jenis=='Aset Laboratorium'){
            $adaDataDipinjamkan = false;
            for($x=0;$x<$jumlah;$x++){
                PeminjamanDetail::find($request->id[$x])->update([
                'stok_pinjam' => $request->stok_pinjam[$x],
                'status' => $request->status[$x],
                'petugas_peminjaman' => $user,
                ]);

            if($request->status[$x]=='Dipinjamkan'){
                $barang = BarangInventaris::find($request->barang_id[$x]);
                $stok = $barang->stok;
                $barang->update([
                    'stok' => $stok-$request->stok_pinjam[$x]
                ]);
                
                Aktifitas::insert([
                    'username' => $peminjam->username,
                    'name' => $peminjam->name,
                    'aktifitas' => 'Peminjaman Alat '.$cek->jenis,
                ]);
                $data = [
                    'username' => $peminjam->username,
                    'nama' => $peminjam->name,
                    'nama_barang' => $barang->nama,
                    'id' => $cek->id,
                    'jenis' => $cek->jenis,
                    'tgl_pinjaman' => $cek->tgl_pinjaman,
                    'tgl_pengembalian' => $cek->tgl_pengembalian
                ];
                $peminjam->notify(new EmailNotification($data));
                $adaDataDipinjamkan = true;
                $cek->update(['status' => 'Menunggu Dikembalikan']);
                }
                
                if (!$adaDataDipinjamkan) {
                    $cek->update(['status' => 'Selesai']);
                }

            }
        }elseif($cek->jenis=='Alat Medis'){
            $adaDataDipinjamkan = false;
            for($x=0;$x<$jumlah;$x++){
                PeminjamanDetail::find($request->id[$x])->update([
                'stok_pinjam' => $request->stok_pinjam[$x],
                'status' => $request->status[$x],
                'petugas_peminjaman' => $user,
            ]);

            if($request->status[$x]=='Dipinjamkan'){
                $barang = BarangBHP_alat::find($request->barang_id[$x]);
                $stok = $barang->stok;
                $barang->update([
                    'stok' => $stok-$request->stok_pinjam[$x]
                ]);
                
                Aktifitas::insert([
                    'username' => $peminjam->username,
                    'name' => $peminjam->name,
                    'aktifitas' => 'Peminjaman Alat '.$cek->jenis,
                ]);
                $data = [
                    'username' => $peminjam->username,
                    'nama' => $peminjam->name,
                    'nama_barang' => $barang->nama,
                    'id' => $cek->id,
                    'jenis' => $cek->jenis,
                    'tgl_pinjaman' => $cek->tgl_pinjaman,
                    'tgl_pengembalian' => $cek->tgl_pengembalian
                ];
                $peminjam->notify(new EmailNotification($data));
                
                $adaDataDipinjamkan = true;
                $cek->update(['status' => 'Menunggu Dikembalikan']);
                }
                
                if (!$adaDataDipinjamkan) {
                    $cek->update(['status' => 'Selesai']);
                }
            }
        }else{
            for($x=0;$x<$jumlah;$x++){
                PeminjamanDetail::find($request->id[$x])->update([
                'stok_pinjam' => $request->stok_pinjam[$x],
                'status' => $request->status[$x],
                'petugas_peminjaman' => $user,
            ]);

            if($request->status[$x]=='Pemakaian'){
                $barang = BarangBHP_bahan::find($request->barang_id[$x]);
                $stok = $barang->stok;
                $barang->update([
                    'stok' => $stok-$request->stok_pinjam[$x]
                ]);
                
                Aktifitas::insert([
                    'username' => $peminjam->username,
                    'name' => $peminjam->name,
                    'aktifitas' => 'Peminjaman Alat '.$cek->jenis,
                ]);
                $data = [
                    'username' => $peminjam->username,
                    'nama' => $peminjam->name,
                    'nama_barang' => $barang->nama,
                    'id' => $cek->id,
                    'jenis' => $cek->jenis,
                    'tgl_pinjaman' => $cek->tgl_pinjaman,
                    'tgl_pengembalian' => $cek->tgl_pengembalian
                ];
                $peminjam->notify(new EmailNotification($data));
                }
                $cek->update(['status' => 'Selesai']);
            
            }
        }

        return redirect('petugas/request?jurusan='.$jurusan)->with('message', 'Data request berhasil diproses.');
    }

    // PENGEMBALIAN _________________________________________________

    public function pengembalian(Request $request)
    {
        $jurusan = $request->jurusan;
        $sql = "SELECT b.name,a.* 
                FROM peminjaman a
                join users b on (a.user_id=b.id)
                where a.jurusan='$jurusan' and a.status!='Menunggu Konfirmasi'
                order by a.id desc";
        $data = DB::select($sql);
        return view('petugas_lab.pengembalian',compact('data','jurusan'));
    }

    public function pengembalian_detail($id)
    {
        $cek = Peminjaman::find($id);
        $jurusan = $cek->jurusan;
        if($cek->jenis=='Aset Laboratorium'){
            $sql = "select a.id, b.jenis, a.barang_id, c.nama, c.satuan, a.kondisi, a.stok_pinjam, b.tgl_pinjaman, b.tgl_pengembalian, a.status
                    from peminjaman_detail a
                    join peminjaman b on (a.peminjaman_id=b.id)
                    join barang_inventaris c on (a.barang_id=c.id)
                    where a.peminjaman_id=$id and a.status!='Ditolak'";
        }elseif($cek->jenis=='Alat Medis'){
            $sql = "select a.id, b.jenis, a.barang_id, c.nama, c.satuan, a.kondisi, a.stok_pinjam, b.tgl_pinjaman, b.tgl_pengembalian, a.status
                    from peminjaman_detail a
                    join peminjaman b on (a.peminjaman_id=b.id)
                    join barangbhp_alat c on (a.barang_id=c.id)
                    where a.peminjaman_id=$id and a.status!='Ditolak'";
        }else{
            $sql = "select a.id, b.jenis, a.barang_id, c.nama, c.satuan, a.kondisi, a.stok_pinjam, b.tgl_pinjaman, b.tgl_pengembalian, a.status
                    from peminjaman_detail a
                    join peminjaman b on (a.peminjaman_id=b.id)
                    join barangbhp_bahan c on (a.barang_id=c.id)
                    where a.peminjaman_id=$id and a.status!='Ditolak'";
        }
        $data = DB::select($sql);
        
        $status = Peminjaman::find($id);
        return view('petugas_lab.pengembalian_detail',compact('data','jurusan','id','status'));
    }

    public function pengembalian_submit(Request $request)
    {
        $cek = Peminjaman::find($request->peminjaman_id);
        $jurusan = $cek->jurusan;
        $user = auth()->user()->name;

        $jumlah = count($request->id);
        if($cek->jenis=='Aset Laboratorium'){
            for($x=0;$x<$jumlah;$x++){
                PeminjamanDetail::find($request->id[$x])->update([
                'status' => $request->status[$x],
                'kondisi' => $request->kondisi[$x],
                'petugas_pengembalian' => $user,
                ]);

            if($request->status[$x]=='Dikembalikan'){
                $barang = BarangInventaris::find($request->barang_id[$x]);
                $stok = $barang->stok;
                $barang->update([
                    'stok' => $stok+$request->stok_pinjam[$x],
                    'kondisi' => $request->kondisi[$x],
                ]);
                }

            }
        }elseif($cek->jenis=='Alat Medis'){
            for($x=0;$x<$jumlah;$x++){
                PeminjamanDetail::find($request->id[$x])->update([
                'status' => $request->status[$x],
                'kondisi' => $request->kondisi[$x],
                'petugas_pengembalian' => $user,
            ]);

            if($request->status[$x]=='Dikembalikan'){
                $barang = BarangBHP_alat::find($request->barang_id[$x]);
                $stok = $barang->stok;
                $barang->update([
                    'stok' => $stok+$request->stok_pinjam[$x],
                    'kondisi' => $request->kondisi[$x],
                ]);
                }
            }
        }else{
            for($x=0;$x<$jumlah;$x++){
                PeminjamanDetail::find($request->id[$x])->update([
                'status' => $request->status[$x],
                'petugas_pengembalian' => $user,
            ]);

            if($request->status[$x]=='Dikembalikan'){
                $barang = BarangBHP_bahan::find($request->barang_id[$x]);
                $stok = $barang->stok;
                $barang->update([
                    'stok' => $stok+$request->stok_pinjam[$x]
                ]);
                }
            }
        }

        $cek->update([
            'status' => 'Selesai'
        ]);

        $peminjam = User::find($cek->user_id);

        Aktifitas::insert([
            'username' => $peminjam->username,
            'name' => $peminjam->name,
            'aktifitas' => 'Pengembalian Alat',
        ]);

        return redirect('petugas/pengembalian?jurusan='.$jurusan)->with('message', 'Data pengembalian berhasil diupdate.');
    }
    
    public function pengembalian_detail2($id)
    {
        $cek = Peminjaman::find($id);
        $jurusan = $cek->jurusan;
        if($cek->jenis=='Aset Laboratorium'){
            $sql = "select a.id, b.jenis, a.barang_id, c.nama, c.satuan, a.kondisi, a.stok_pinjam, b.tgl_pinjaman, b.tgl_pengembalian, a.status
                    from peminjaman_detail a
                    join peminjaman b on (a.peminjaman_id=b.id)
                    join barang_inventaris c on (a.barang_id=c.id)
                    where a.peminjaman_id=$id and a.status!='Ditolak'";
        }elseif($cek->jenis=='Alat Medis'){
            $sql = "select a.id, b.jenis, a.barang_id, c.nama, c.satuan, a.kondisi, a.stok_pinjam, b.tgl_pinjaman, b.tgl_pengembalian, a.status
                    from peminjaman_detail a
                    join peminjaman b on (a.peminjaman_id=b.id)
                    join barangbhp_alat c on (a.barang_id=c.id)
                    where a.peminjaman_id=$id and a.status!='Ditolak'";
        }else{
            $sql = "select a.id, b.jenis, a.barang_id, c.nama, c.satuan, a.kondisi, a.stok_pinjam, b.tgl_pinjaman, b.tgl_pengembalian, a.status
                    from peminjaman_detail a
                    join peminjaman b on (a.peminjaman_id=b.id)
                    join barangbhp_bahan c on (a.barang_id=c.id)
                    where a.peminjaman_id=$id and a.status!='Ditolak'";
        }
        $data = DB::select($sql);
        
        $status = Peminjaman::find($id);
        return view('petugas_lab.pengembalian_detail2',compact('data','jurusan','id','status'));
    }
    
    public function pengembalian_submit2(Request $request)
    {
        //dd($request);
        $cek = Peminjaman::find($request->peminjaman_id);
        $jurusan = $cek->jurusan;
        $user = auth()->user()->name;

        $data = PeminjamanDetail::find($request->id);

        if($request->stok_pinjam == $request->stok_pengembalian)
        {
            PeminjamanDetail::find($request->id)->update([
                'status' => $request->status,
                'kondisi' => $request->kondisi,
                'petugas_pengembalian' => $user,
            ]);

            if($cek->jenis=='Aset Laboratorium'){
                if($request->status=='Dikembalikan'){
                    $barang = BarangInventaris::find($data->barang_id);
                    $stok = $barang->stok;
                    $barang->update([
                        'stok' => $stok+$request->stok_pengembalian,
                        'kondisi' => $request->kondisi,
                    ]);
                }
            } elseif($cek->jenis=='Alat Medis') {
                if($request->status=='Dikembalikan'){
                    $barang = BarangBHP_alat::find($request->barang_id);
                    $stok = $barang->stok;
                    $barang->update([
                        'stok' => $stok+$request->stok_pengembalian,
                        'kondisi' => $request->kondisi,
                    ]);
                }
            } else {
                dd("Barag BHP Tidak Perlu Dikembalikan");
            }
        } else {
            PeminjamanDetail::insert([
            'peminjaman_id'=>$data->peminjaman_id,
            'barang_id'=>$data->barang_id,
            'stok_pinjam'=>$request->stok_pengembalian,
            'status'=>$request->status,
            'kondisi' => $request->kondisi,
            'petugas_peminjaman' => $data->petugas_peminjaman,
            'petugas_pengembalian' => $user,
            ]);

            PeminjamanDetail::find($request->id)->update([
            'stok_pinjam'=> $data->stok_pinjam - $request->stok_pengembalian
            ]);

            if($cek->jenis=='Aset Laboratorium'){
                if($request->status=='Dikembalikan'){
                $barang = BarangInventaris::find($data->barang_id);
                $stok = $barang->stok;
                $barang->update([
                    'stok' => $stok+$request->stok_pengembalian,
                    'kondisi' => $request->kondisi,
                ]);
                }
            } elseif($cek->jenis=='Alat Medis') {
                if($request->status=='Dikembalikan'){
                $barang = BarangBHP_alat::find($request->barang_id);
                $stok = $barang->stok;
                $barang->update([
                    'stok' => $stok+$request->stok_pengembalian,
                    'kondisi' => $request->kondisi,
                ]);
                }
            } else {
                dd("Barag BHP Tidak Perlu Dikembalikan");
            }
        }

        $cek_status = DB::select("select *
                        from peminjaman_detail
                        where peminjaman_id=$request->peminjaman_id and status='Dipinjamkan'");
        if(!$cek_status){
            $cek->update([
            'status' => 'Selesai'
        ]);
        }
        
        $peminjam = User::find($cek->user_id);

        Aktifitas::insert([
            'username' => $peminjam->username,
            'name' => $peminjam->name,
            'aktifitas' => 'Pengembalian Alat',
        ]);

        return redirect('petugas/pengembalian_detail2/'.$request->peminjaman_id)->with('message', 'Data pengembalian berhasil diupdate.');
    }

    public function belum_dikembalikan(Request $request)
    {
        $jurusan = $request->jurusan;

        $alat_lab = DB::select("select d.name, b.jenis, b.ruangan, b.tgl_pinjaman, b.tgl_pengembalian, b.penanggung_jawab, b.tingkat, 
                c.nama, c.satuan, a.stok_pinjam, a.petugas_peminjaman, a.petugas_pengembalian, a.status
                from peminjaman_detail a
                join peminjaman b on (a.peminjaman_id=b.id)
                join barang_inventaris c on (a.barang_id=c.id)
                join users d on (b.user_id=d.id)
                where b.jurusan='$jurusan' and b.jenis='Aset Laboratorium' and a.status='Dipinjamkan'
                order by a.id");
        $alat_medis = DB::select("select d.name, b.jenis, b.ruangan, b.tgl_pinjaman, b.tgl_pengembalian, b.penanggung_jawab, b.tingkat, 
                c.nama, c.satuan, a.stok_pinjam, a.petugas_peminjaman, a.petugas_pengembalian, a.status
                from peminjaman_detail a
                join peminjaman b on (a.peminjaman_id=b.id)
                join barangbhp_alat c on (a.barang_id=c.id)
                join users d on (b.user_id=d.id)
                where b.jurusan='$jurusan' and b.jenis='Alat Medis' and a.status='Dipinjamkan'
                order by a.id");

        return view('petugas_lab.belum_dikembalikan',compact('jurusan','alat_lab','alat_medis'));
    }

    // LAPORAN __________________________________________________________________________

    public function lap_index(Request $request)
    {
        $jurusan = $request->jurusan;
        return view('petugas_lab.lap_index',compact('jurusan'));
    }

    public function lap_alat_lab(Request $request)
    {
        $jurusan = $request->jurusan;
        $data = BarangInventaris::where('jurusan','=',$jurusan)->whereNull('delete')->OrderBy('nama')->get();
        return view('petugas_lab.lap_alat_lab',compact('jurusan','data'));
    }

    public function lap_alat_medis(Request $request)
    {
        $jurusan = $request->jurusan;
        $data = BarangBHP_alat::where('jurusan','=',$jurusan)->whereNull('delete')->OrderBy('nama')->get();
        return view('petugas_lab.lap_alat_medis',compact('jurusan','data'));
    }

    public function lap_bahan_bhp(Request $request)
    {
        $jurusan = $request->jurusan;
        $statusExpired = $request->get('status_expired', 'all');

        $query = BarangBHP_bahan::where('jurusan', $jurusan)->whereNull('delete');

        if ($statusExpired === 'expired') {
            $query->whereRaw('tgl_expired < CURDATE()');
        } elseif ($statusExpired === 'not_expired') {
            $query->whereRaw('tgl_expired >= CURDATE()');
        }
        // 'all' = no extra where

        $data = $query->orderBy('nama')->get();

        return view('petugas_lab.lap_bahan_bhp', compact('jurusan', 'data', 'statusExpired'));
    }

    public function lap_penambahan_alat_lab(Request $request)
    {
        $jurusan = $request->jurusan;

        if($request->tgl_awal){
            $tgl_awal = $request->tgl_awal;
            $tgl_akhir = $request->tgl_akhir;

            $sql = "select a.created_at, b.nama, b.satuan, b.kode_barang, a.stok, a.petugas, a.keterangan
                from penambahan_barang a
                join barang_inventaris b on (a.barang_id=b.id)
                where b.jurusan='$jurusan' and jenis_barang='Aset Laboratorium' and 
                created_at BETWEEN '$tgl_awal 00:00:01' AND '$tgl_akhir 23:59:59' ";
            $data = DB::select($sql);
        }else{
            $tgl_awal = '';
            $tgl_akhir = '';
            $data='';
        }

        return view('petugas_lab.lap_penambahan_alat_lab',compact('jurusan','data','tgl_awal','tgl_akhir'));
    }

    public function lap_penambahan_alat_medis(Request $request)
    {
        $jurusan = $request->jurusan;

        if($request->tgl_awal){
            $tgl_awal = $request->tgl_awal;
            $tgl_akhir = $request->tgl_akhir;

            $sql = "select a.created_at, b.nama, b.satuan, a.stok, a.petugas, a.keterangan
                from penambahan_barang a
                join barangbhp_alat b on (a.barang_id=b.id)
                where b.jurusan='$jurusan' and jenis_barang='Alat Medis' and 
                created_at BETWEEN '$tgl_awal 00:00:01' AND '$tgl_akhir 23:59:59' ";
            $data = DB::select($sql);
        }else{
            $tgl_awal = '';
            $tgl_akhir = '';
            $data='';
        }

        return view('petugas_lab.lap_penambahan_alat_medis',compact('jurusan','data','tgl_awal','tgl_akhir'));
    }

    public function lap_penambahan_bahan_bhp(Request $request)
    {
        $jurusan = $request->jurusan;

        if($request->tgl_awal){
            $tgl_awal = $request->tgl_awal;
            $tgl_akhir = $request->tgl_akhir;

            $sql = "select a.created_at, b.nama, b.satuan, a.stok, a.petugas, a.keterangan
                from penambahan_barang a
                join barangbhp_bahan b on (a.barang_id=b.id)
                where b.jurusan='$jurusan' and jenis_barang='Bahan Medis' and 
                created_at BETWEEN '$tgl_awal 00:00:01' AND '$tgl_akhir 23:59:59' ";
            $data = DB::select($sql);
        }else{
            $tgl_awal = '';
            $tgl_akhir = '';
            $data='';
        }

        return view('petugas_lab.lap_penambahan_bahan_bhp',compact('jurusan','data','tgl_awal','tgl_akhir'));
    }

    public function lap_pinjaman_alat_lab(Request $request)
    {
        $jurusan = $request->jurusan;

        if($request->tgl_awal){
            $tgl_awal = $request->tgl_awal;
            $tgl_akhir = $request->tgl_akhir;

            $sql = "select d.name, b.jenis, b.ruangan, b.tgl_pinjaman, b.tgl_pengembalian, b.penanggung_jawab, b.tingkat, c.nama, c.satuan,
                a.stok_pinjam, a.petugas_peminjaman, a.petugas_pengembalian, a.status, a.kondisi
                from peminjaman_detail a
                join peminjaman b on (a.peminjaman_id=b.id)
                join barang_inventaris c on (a.barang_id=c.id)
                join users d on (b.user_id=d.id)
                where b.jurusan='$jurusan' and b.jenis='Aset Laboratorium' and a.status!='Ditolak' and a.status!='Menunggu Konfirmasi'
                and b.tgl_pinjaman BETWEEN '$tgl_awal' AND '$tgl_akhir'
                order by a.id";
            $data = DB::select($sql);
        }else{
            $tgl_awal = '';
            $tgl_akhir = '';
            $data='';
        }

        return view('petugas_lab.lap_pinjaman_alat_lab',compact('jurusan','data','tgl_awal','tgl_akhir'));
    }

    public function lap_pinjaman_alat_medis(Request $request)
    {
        $jurusan = $request->jurusan;

        if($request->tgl_awal){
            $tgl_awal = $request->tgl_awal;
            $tgl_akhir = $request->tgl_akhir;

            $sql = "select d.name, b.jenis, b.ruangan, b.tgl_pinjaman, b.tgl_pengembalian, b.penanggung_jawab, b.tingkat, c.nama, c.satuan, a.stok_pinjam, 
                a.petugas_peminjaman, a.petugas_pengembalian, a.status, a.kondisi
                from peminjaman_detail a
                join peminjaman b on (a.peminjaman_id=b.id)
                join barangbhp_alat c on (a.barang_id=c.id)
                join users d on (b.user_id=d.id)
                where b.jurusan='$jurusan' and b.jenis='Alat Medis' and a.status!='Ditolak' and a.status!='Menunggu Konfirmasi' and  
                b.tgl_pinjaman BETWEEN '$tgl_awal' AND '$tgl_akhir'
                order by a.id";
            $data = DB::select($sql);
        }else{
            $tgl_awal = '';
            $tgl_akhir = '';
            $data='';
        }

        return view('petugas_lab.lap_pinjaman_alat_medis',compact('jurusan','data','tgl_awal','tgl_akhir'));
    }

    public function lap_pinjaman_bahan_bhp(Request $request)
    {
        $jurusan = $request->jurusan;

        if($request->tgl_awal){
            $tgl_awal = $request->tgl_awal;
            $tgl_akhir = $request->tgl_akhir;

            $sql = "select d.name, b.jenis, b.ruangan, b.tgl_pinjaman, b.tgl_pengembalian, b.penanggung_jawab, b.tingkat,
                c.nama, c.satuan, a.stok_pinjam, a.petugas_peminjaman, a.petugas_pengembalian, a.status
                from peminjaman_detail a
                join peminjaman b on (a.peminjaman_id=b.id)
                join barangbhp_bahan c on (a.barang_id=c.id)
                join users d on (b.user_id=d.id)
                where b.jurusan='$jurusan' and b.jenis='Bahan Medis' and a.status!='Ditolak' and a.status!='Menunggu Konfirmasi'
                and b.tgl_pinjaman BETWEEN '$tgl_awal' AND '$tgl_akhir'
                order by a.id";
            $data = DB::select($sql);
        }else{
            $tgl_awal = '';
            $tgl_akhir = '';
            $data='';
        }

        return view('petugas_lab.lap_pinjaman_bahan_bhp',compact('jurusan','data','tgl_awal','tgl_akhir'));
    }

    public function lap_kunjungan_lab(Request $request)
    {
        $jurusan = $request->jurusan;
        if($request->tgl_awal){
            $tgl_awal = $request->tgl_awal;
            $tgl_akhir = $request->tgl_akhir;

            $sql = "select a.*, b.name
            from kunjungan_lab a
            join users b on (a.user_id=b.id)
            where a.jurusan='$jurusan' and a.tgl_kunjungan BETWEEN '$tgl_awal' AND '$tgl_akhir'
            order by a.id desc";
            $data = DB::select($sql);
        }else{
            $tgl_awal = '';
            $tgl_akhir = '';
            $data='';
        }

        $sql = "";

        $jurusan = $request->jurusan;
        return view('petugas_lab.lap_kunjungan_lab',compact('jurusan','data','tgl_awal','tgl_akhir'));
    }
    
    public function lap_penggunaan_lab(Request $request)
    {
        $jurusan = $request->jurusan;
        if($request->tgl_awal){
            $tgl_awal = $request->tgl_awal;
            $tgl_akhir = $request->tgl_akhir;

            $sql = "select *
            from jadwal_praktikum 
            where jurusan='$jurusan' and tgl BETWEEN '$tgl_awal' AND '$tgl_akhir'
            order by ruang,tgl,waktu_mulai asc";
            $data = DB::select($sql);
        }else{
            $tgl_awal = '';
            $tgl_akhir = '';
            $data='';
        }

        $sql = "";

        $jurusan = $request->jurusan;
        return view('petugas_lab.lap_penggunaan_lab',compact('jurusan','data','tgl_awal','tgl_akhir'));
    }

    public function penambahan_stok_barang(Request $request)
    {
        // dd($request);
        $validatedData = $request->validate([
            'barang_id' => ['required'],
            'stok' => ['required'],
            'jenis_barang' => ['required'],
        ]);
        $user = Auth()->user()->name;

        if($request->jenis_barang=='Aset Laboratorium'){
            PenambahanBarang::insert([
                'jenis_barang' => 'Aset Laboratorium',
                'barang_id' => $request->barang_id,
                'stok' => $request->stok,
                'keterangan' => $request->keterangan,
                'petugas' => $user,
            ]);

            $barang = BarangInventaris::find($request->barang_id);
            $stok = $barang->stok;
            $barang->update([
                'stok' => $stok+$request->stok
            ]);
        }elseif($request->jenis_barang=='Alat Medis'){
            PenambahanBarang::insert([
                'jenis_barang' => 'Alat Medis',
                'barang_id' => $request->barang_id,
                'stok' => $request->stok,
                'keterangan' => $request->keterangan,
                'petugas' => $user,
            ]);

            $barang = BarangBHP_alat::find($request->barang_id);
            $stok = $barang->stok;
            $barang->update([
                'stok' => $stok+$request->stok
            ]);
        }elseif($request->jenis_barang=='Bahan Medis'){
            PenambahanBarang::insert([
                'jenis_barang' => 'Bahan Medis',
                'barang_id' => $request->barang_id,
                'stok' => $request->stok,
                'keterangan' => $request->keterangan,
                'tgl_expired' => $request->tgl_expired,
                'petugas' => $user,
            ]);

            $barang = BarangBHP_bahan::find($request->barang_id);
            $stok = $barang->stok;
            $barang->update([
                'stok' => $stok+$request->stok,
                'tgl_expired' => $request->tgl_expired
            ]);
        }else{
            dd('Jenis Barang Tidak Terdaftar');
        }
        

        return redirect()->back()->with('message', 'Data stok barang berhasil ditambahkan.'); 
    }


































    public function xxxrequest()
    {
        $sql = "select a.*, b.kode_barang, b.nama_barang, b.kategori, c.name
                from pinjaman_pengembalian a
                join barang b on (a.kode_barang=b.kode_barang)
                join users c on (a.username=c.username)
                where a.status='Menunggu Konfirmasi' order by a.tgl_request desc";
        $data = DB::select($sql);
        $title = 'Permintaan Peminjaman Barang';
        return view('petugas_lab.request',compact('data','title'));
    }

    public function edit_request(Request $request)
    {
        $petugas = auth()->user()->name;
        $validatedData = $request->validate([
            'stok_pinjaman' => ['required'],
            'keperluan' => ['required'],
            'status' => ['required']
        ]);

        if($request->status=='Ditolak'){
            Pinjaman::find($request->id)->update([
                'stok_pinjaman' => $request->stok_pinjaman,
                'keperluan' => $request->keperluan,
                'status' => $request->status,
                'tgl_pinjaman' => $request->tgl_pinjaman,
                'tgl_pengembalian' => $request->tgl_pengembalian,
                'keterangan' => $request->keterangan,
                'petugas' => $petugas,
            ]);
            return redirect('petugas/request')->with('message', 'Data request berhasil diupdate.');
        }else{
            if($request->kategori != 'Perlengkapan Sekali Pakai'){
                Pinjaman::find($request->id)->update([
                    'stok_pinjaman' => $request->stok_pinjaman,
                    'keperluan' => $request->keperluan,
                    'status' => $request->status,
                    'tgl_pinjaman' => $request->tgl_pinjaman,
                    'tgl_pengembalian' => $request->tgl_pengembalian,
                    'keterangan' => $request->keterangan,
                    'petugas' => $petugas,
                ]);

                if($request->status=='Sudah Dikembalikan'){
                    TransaksiPinjaman::find($request->id)->delete();

                    Aktifitas::insert([
                        'username' => $request->username,
                        'name' => $request->name,
                        'aktifitas' => 'Pengembalian Barang',
                    ]);
                }else{
                    TransaksiPinjaman::insert([
                        'id_pinjaman' => $request->id,
                        'stok_pinjaman' => $request->stok_pinjaman,
                        'kode_barang' => $request->kode_barang,
                    ]);

                    Aktifitas::insert([
                        'username' => $request->username,
                        'name' => $request->name,
                        'aktifitas' => 'Peminjaman Barang',
                    ]);
                }

            }else{
                Pinjaman::find($request->id)->update([
                    'stok_pinjaman' => $request->stok_pinjaman,
                    'keperluan' => $request->keperluan,
                    'status' => $request->status,
                    'tgl_pinjaman' => $request->tgl_pinjaman,
                    'keterangan' => $request->keterangan,
                    'petugas' => $petugas,
                ]);

                $barang = Barang::where('kode_barang','=',$request->kode_barang)->first();
                $barang->update([
                    'stok' => $barang->stok - $request->stok_pinjaman,
                    'baik' => $barang->stok - $request->stok_pinjaman,
                ]);

                Aktifitas::insert([
                        'username' => $request->username,
                        'name' => $request->name,
                        'aktifitas' => 'Peminjaman Barang',
                    ]);
            }

            return redirect('petugas/peminjaman')->with('message', 'Data request berhasil diupdate.');
        }

    }

    public function peminjaman()
    {
        $sql = "select a.*, b.kode_barang, b.nama_barang, b.kategori, c.name
                from pinjaman_pengembalian a
                join barang b on (a.kode_barang=b.kode_barang)
                join users c on (a.username=c.username)
                where a.status!='Menunggu Konfirmasi' order by a.tgl_request desc";
        $data = DB::select($sql);
        $title = 'Data Peminjaman & Pengembalian';
        return view('petugas_lab.request',compact('data','title'));
    }

    public function ketersediaan()
    {
        $sql = "SELECT a.kode_barang, a.nama_barang, a.kategori, COALESCE(a.baik, 0) as baik, 
            COALESCE(SUM(b.stok_pinjaman), 0) as stok_pinjaman
            FROM barang a
            LEFT JOIN transaksi_pinjaman b ON a.kode_barang = b.kode_barang
            where a.status='active'
            GROUP BY a.kode_barang, a.nama_barang, a.baik, a.kategori";
        $barang = DB::select($sql);
        return view('petugas_lab.ketersediaan',compact('barang'));
    }

    public function detail_pinjaman($kode)
    {
        $sql = "select a.*,c.username, c.name,d.nama_barang
                from transaksi_pinjaman a
                join pinjaman_pengembalian b on (a.id_pinjaman=b.id)
                join users c on (b.username=c.username)
                join barang d on (a.kode_barang=d.kode_barang)
                where a.kode_barang='$kode'";
        $data = DB::select($sql);
        return view('petugas_lab.detail_pinjaman',compact('data','kode'));

    }

    public function barang_masuk()
    {
        $barang = Barang::OrderBy('kode_barang')->get();

        $sql = "select a.*, b.nama_barang
                from barang_masuk a
                join barang b on (a.kode_barang=b.kode_barang)
                where a.updated_at is null";
        $data = DB::select($sql);
        return view('petugas_lab.barang_masuk',compact('barang','data'));
    }

    public function add_barang_masuk(Request $request)
    {
        $petugas = auth()->user()->name;
        $this->validate($request, [
            'kode_barang' => ['required'],
            'stok_masuk' => ['required'],
        ]);

        BarangMasuk::create([
            'kode_barang' => $request->kode_barang,
            'stok_masuk' => $request->stok_masuk,
            'petugas' => $petugas
        ]);

        return redirect('petugas/barang_masuk')->with("message","Data barang masuk berhasil ditambah");
    }

    public function edit_barang_masuk(Request $request)
    {
        $petugas = auth()->user()->name;
        $this->validate($request, [
            'kode_barang' => ['required'],
            'stok_masuk' => ['required'],
        ]);

        BarangMasuk::find($request->id)->update([
            'kode_barang' => $request->kode_barang,
            'stok_masuk' => $request->stok_masuk,
            'petugas' => $petugas
        ]);

        return redirect('petugas/barang_masuk')->with("message","Data barang masuk berhasil diupdate");
    }

    public function delete_barang_masuk(Request $request)
    {
        BarangMasuk::find($request->id)->delete();
        return redirect('petugas/barang_masuk')->with("message","Data barang masuk berhasil didelete");
    }

    public function update_barang_masuk(Request $request)
    {
        $petugas = auth()->user()->name;
        $tgl = date("Y-m-d H:i:s");
        for($x=0;$x<$request->jumlah_data;$x++){
            $data = Barang::where('kode_barang','=',$request->kode_barang[$x])->first();
            $data->update([
            'stok'=>$data->stok+$request->stok_masuk[$x],
            'baik'=>$data->baik+$request->stok_masuk[$x],
            ]);

            BarangMasuk::find($request->id[$x])->update([
                'updated_at' => $tgl,
                'petugas' => $petugas
            ]);
        }

        return redirect('petugas/barang_masuk')->with("message","Data stok barang berhasil ditambahkan");
    }

    public function laporan_barang_masuk(Request $request)
    {
        if($request->tgl_awal){
            $tgl_awal = $request->tgl_awal;
            $tgl_akhir = $request->tgl_akhir;
            $laporan_barang_masuk = DB::select("select a.*, b.nama_barang, b.kode_barang
            from barang_masuk a 
            join barang b on (a.kode_barang=b.kode_barang)
            where a.updated_at between '$tgl_awal 00:00:01' and '$tgl_akhir 23:59:59'
            order by a.updated_at");
        } else {
            $laporan_barang_masuk = '';
            $tgl_awal = '';
            $tgl_akhir = '';
        }

        return view('petugas_lab.laporan_barang_masuk',compact('laporan_barang_masuk','tgl_awal','tgl_akhir'));
    }

    public function print_lap_bm(Request $request)
    {
        $tgl_awal = $request->tgl_awal;
        $tgl_akhir = $request->tgl_akhir;
        $laporan_barang_masuk = DB::select("select a.*, b.nama_barang, b.kode_barang
        from barang_masuk a 
        join barang b on (a.kode_barang=b.kode_barang)
        where a.updated_at between '$tgl_awal 00:00:01' and '$tgl_akhir 23:59:59'
        order by a.updated_at");

        // dd($laporan_barang_masuk);
        $data = PDF::loadview('petugas_lab.print_lap_bm',compact('laporan_barang_masuk','tgl_awal','tgl_akhir'));
        return $data->stream();
    }

    public function laporan_pinjaman(Request $request)
    {
        if($request->tgl_awal){
            $tgl_awal = $request->tgl_awal;
            $tgl_akhir = $request->tgl_akhir;
            $laporan_pinjaman = DB::select("select a.*, b.kode_barang, b.nama_barang, b.kategori, c.name
                from pinjaman_pengembalian a
                join barang b on (a.kode_barang=b.kode_barang)
                join users c on (a.username=c.username)
                where a.status!='Menunggu Konfirmasi' and 
                a.tgl_pinjaman between '$tgl_awal 00:00:01' and '$tgl_akhir 23:59:59'
                order by a.tgl_pinjaman desc");
        } else {
            $laporan_pinjaman = '';
            $tgl_awal = '';
            $tgl_akhir = '';
        }

        return view('petugas_lab.laporan_pinjaman',compact('laporan_pinjaman','tgl_awal','tgl_akhir'));
    }

    public function print_lap_pinjaman(Request $request)
    {
        $tgl_awal = $request->tgl_awal;
        $tgl_akhir = $request->tgl_akhir;
        $laporan_pinjaman = DB::select("select a.*, b.kode_barang, b.nama_barang, b.kategori, c.name
            from pinjaman_pengembalian a
            join barang b on (a.kode_barang=b.kode_barang)
            join users c on (a.username=c.username)
            where a.status!='Menunggu Konfirmasi' and 
            a.tgl_pinjaman between '$tgl_awal 00:00:01' and '$tgl_akhir 23:59:59'
            order by a.tgl_pinjaman desc");

        $data = PDF::loadview('petugas_lab.print_lap_pinjaman',compact('laporan_pinjaman','tgl_awal','tgl_akhir'));
        return $data->stream();
    }

}
