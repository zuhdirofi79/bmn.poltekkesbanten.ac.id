<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\UsersImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use App\User;
use App\HasRole;
use App\Semester;
use App\Kelas;
use App\Tingkat;
use App\Ruangan;
use DB;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    //USER______________________________________________________________________________

    public function users()
    {
        $user = User::all();
        return view('admin.users',compact('user'));
    }

    public function user_detail($id)
    {
        $user = User::find($id);
        $sql = "select c.name
                from users a
                join model_has_roles b on (a.id=b.model_id)
                join roles c on (b.role_id=c.id)
                where b.model_id=$id";
        $roles = DB::select($sql);
        return view('admin.user_detail',compact('user','roles'));
    }

    public function user_update(Request $request)
    {
        $validatedData = $request->validate([
            'id' => ['required'],
            'username' => ['required'],
            'name' => ['required'],
            'email' => ['required', 'email'],
            'jenis_kelamin' => ['required']
        ]);

        if($request->jenis_kelamin == 'Laki-laki'){
            $foto = 'male.png';
        } else {
            $foto = 'female.png';
        }

        $user = User::find($request->id)->update([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'jenis_kelamin' => $request->jenis_kelamin,
            'jurusan' => $request->jurusan,
            'foto_profile' => $foto,
            'no_hp' => $request->no_hp
        ]);
        return redirect()->back()->with('message', 'Profile user Berhasil di update.');
    }

    public function user_reset(Request $request)
    {
        if(strcmp($request->get('current-password'), $request->get('new-password')) == 0){
        //Current password and new password are same
        return redirect()->back()->with("error","Kata Sandi Baru tidak boleh sama dengan kata sandi Anda saat ini. Silakan pilih kata sandi lain.");
        }
        if(!(strcmp($request->get('new-password'), $request->get('new-password-confirm'))) == 0){
            //New password and confirm password are not same
            return redirect()->back()->with("error","Kata Sandi Baru harus sama dengan kata sandi Anda yang telah dikonfirmasi. Silakan ketik ulang kata sandi baru.");
        }
        //Change Password
        $user = User::find($request->id);
        $user->password = bcrypt($request->get('new-password'));
        $user->save();
         
        return redirect()->back()->with("success","Password changed successfully !");
    }



    public function user_manage(Request $request)
    {
        $results = User::where(function($query) use ($request) {
            if ( ($term = $request->get("term")) )
            {
                $keywords = '%' . $term . '%';
                $query->orWhere("name", 'LIKE', $keywords);
            }
        })
        ->orderBy('id','desc')->get();

        return view('admin.user_manage',compact('results'));
    }

    public function add_user(Request $request)
    {
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'role' => ['required'],
            'jurusan' => ['required'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'jurusan' => $request->jurusan,
            'role' => $request->role,
            'password' => Hash::make($request->password),
            'foto_profile' => 'default.png',
        ]);

        $user->assignRole($request->role);
        return redirect()->back()->with('message', 'User berhasil ditambahkan.');
    }


    public function user_edit($id)
    {

        $user = User::find($id);
        $role = DB::select("select a.* from roles a where not exists (select role_id from model_has_roles b WHERE model_id='$id' and b.role_id = a.id)");
        $role_have = DB::select("select a.role_id,b.name from model_has_roles a join roles b on (a.role_id=b.id) where a.model_id='$id'");
        return view('admin.user_edit',compact('user','role','role_have','id'));
    }
    
    public function delete_user($id_encrypt)
    {
        $id = Crypt::decrypt($id_encrypt);
        DB::delete("delete from model_has_roles where model_id=$id");
        DB::delete("delete from users where id=$id");
        return back()->with('success', 'Data user berhasil dihapus.');
    }

    public function add_role_user($model_id,$role_id)
    {
        HasRole::create([
            'model_id'=>$model_id,
            'model_type'=>'App\User',
            'role_id'=>$role_id
        ]);
        return back()->with('success', 'Akses berhasil ditambahkan!');
    }

    public function delete_role_user($model_id,$role_id)
    {
        HasRole::where('model_id','=',$model_id)->where('role_id','=',$role_id)->delete();
        return back()->with('success', 'Akses berhasil dihapus!');
    }

    public function import_user()
    {
        return view('admin.import_user');
    }

    public function import(Request $request)
    {
        Excel::import(new UsersImport, $request->file('file'));
        DB::delete("delete from users_import where name = 'NAMA'");
        DB::insert("insert into users (name,email,username,foto_profile,jurusan,role,password,created_at,updated_at)
                select a.name,a.email,a.username,a.foto_profile,a.jurusan,a.role,a.password,a.created_at,a.updated_at
                from users_import a
                left join users b on (a.username=b.username)
                where b.username is null");
        $app = 'App\\\User';
        DB::insert("insert into model_has_roles(role_id,model_type,model_id)
                select c.id as role_id,'$app' as model_type, a.id as model_id
                from users a
                left join model_has_roles b on (b.model_id=a.id)
                join roles c on (a.role=c.name)
                where b.model_id is null");
        DB::delete("delete from users_import");
        return redirect('admin/users')->with('success', 'Data user berhasil di import');
    }

    //MASTER DATA______________________________________________________________________________

    public function master_data()
    {
        $kelas = Kelas::orderBy('jurusan','asc')->orderBy('matkul','asc')->get();
        $semester = Semester::all();
        $ruangan = Ruangan::orderBy('jurusan','asc')->orderBy('nama_ruang','asc')->get();
        $tingkat= Tingkat::orderBy('tingkat','asc')->get();
        return view('admin.master_data',compact('semester','kelas','ruangan','tingkat'));
    }

    public function add_ruangan(Request $request)
    {
        $validatedData = $request->validate([
            'jurusan' => ['required'],
            'kampus' => ['required'],
            'nama_ruang' => ['required'],
        ]);

        Ruangan::insert([
            'jurusan' => $request->jurusan,
            'kampus' => $request->kampus,
            'nama_ruang' => $request->nama_ruang,
        ]);
        return back()->with('success', 'Data Ruangan berhasil ditambah!');
    }

    public function edit_ruangan(Request $request)
    {
        $validatedData = $request->validate([
            'jurusan' => ['required'],
            'kampus' => ['required'],
            'nama_ruang' => ['required'],
        ]);

        Ruangan::find($request->id)->update([
            'jurusan' => $request->jurusan,
            'kampus' => $request->kampus,
            'nama_ruang' => $request->nama_ruang,
        ]);
        return back()->with('success', 'Data Ruangan berhasil diubah!');
    }
    
    public function delete_ruangan($id_encrypt)
    {
        $id = Crypt::decrypt($id_encrypt);
        Ruangan::find($id)->delete();
        return back()->with('success', 'Data Ruangan berhasil dihapus.');
    }

    public function add_kelas(Request $request)
    {
        $validatedData = $request->validate([
            'jurusan' => ['required'],
            'matkul' => ['required'],
        ]);

        Kelas::insert([
            'jurusan' => $request->jurusan,
            'matkul' => $request->matkul,
        ]);
        return back()->with('success', 'Data Kelas berhasil ditambah!');
    }

    public function edit_kelas(Request $request)
    {
        $validatedData = $request->validate([
            'jurusan' => ['required'],
            'matkul' => ['required'],
        ]);

        Kelas::find($request->id)->update([
            'jurusan' => $request->jurusan,
            'matkul' => $request->matkul,
        ]);
        return back()->with('success', 'Data Kelas berhasil diubah!');
    }
    
    public function delete_kelas($id_encrypt)
    {
        $id = Crypt::decrypt($id_encrypt);
        Kelas::find($id)->delete();
        return back()->with('success', 'Data Kelas berhasil dihapus.');
    }

    public function add_tingkat(Request $request)
    {
        $validatedData = $request->validate([
            'tingkat' => ['required'],
        ]);

        Tingkat::insert([
            'tingkat' => $request->tingkat,
        ]);
        return back()->with('success', 'Data Tingkat berhasil ditambah!');
    }

    public function edit_tingkat(Request $request)
    {
        $validatedData = $request->validate([
            'tingkat' => ['required'],
        ]);

        Tingkat::find($request->id)->update([
            'tingkat' => $request->tingkat,
        ]);
        return back()->with('success', 'Data Tingkat berhasil diubah!');
    }
    
    public function delete_tingkat($id_encrypt)
    {
        $id = Crypt::decrypt($id_encrypt);
        Tingkat::find($id)->delete();
        return back()->with('success', 'Data Tingkat berhasil dihapus.');
    }

    public function add_semester(Request $request)
    {
        $validatedData = $request->validate([
            'semester' => ['required'],
            'ta' => ['required'],
            'status' => ['required'],
        ]);

        Semester::insert([
            'semester' => $request->semester,
            'ta' => $request->ta,
            'status' => $request->status,
        ]);
        return back()->with('success', 'Data Semester berhasil ditambah!');
    }

    public function edit_semester(Request $request)
    {
        $validatedData = $request->validate([
            'semester' => ['required'],
            'ta' => ['required'],
            'status' => ['required'],
        ]);

        Semester::find($request->id)->update([
            'semester' => $request->semester,
            'ta' => $request->ta,
            'status' => $request->status,
        ]);
        return back()->with('success', 'Data Semester berhasil diubah!');
    }














    public function icon()
    {
        $icon = DB::select("select * from icon");
        return view('admin.icon',compact('icon'));
    }

    public function icon2()
    {
        $icon = DB::select("select * from icon");
        return view('admin.icon2',compact('icon'));
    }

    public function delete_icon(Request $request)
    {
        $jum = count($request->id);
        for ($i=0; $i < $jum; $i++) { 
            $id = $request->id[$i];
            DB::select("DELETE FROM `icon` WHERE `id` = $id");
        }

        return redirect('admin/icon');
    }

}
