<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PDF;
use App\User;
use DB;
use Image;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('home');
    }

    public function lap_pdf()
    {
        $data = PDF::loadview('laporan_pdf', ['data' => 'ini adalah contoh laporan PDF']);
        return $data->stream();
        // return $data->download('contoh_data.pdf');
    }

    public function blank()
    {
        return view('layouts.thema');
    }

    public function profile()
    {
        $user = Auth::user();
        $sql = "select c.name
                from users a
                join model_has_roles b on (a.id=b.model_id)
                join roles c on (b.role_id=c.id)
                where b.model_id=$user->id";
        $roles = DB::select($sql);
        return view('profile',compact('user','roles'));
    }

    public function update_profile(Request $request)
    {
        $id=Auth::user()->id;
        $validatedData = $request->validate([
            // 'name' => ['required'],
            'email' => ['required', 'email'],
            'jenis_kelamin' => ['required'],
            'no_hp' => ['required']
        ]);

        // dd($request); 

        if($request->jenis_kelamin == 'Laki-laki'){
            $foto = 'male.png';
        } else {
            $foto = 'female.png';
        }

        $user = User::find($id)->update([
            // 'name' => $request->name,
            'email' => $request->email,
            'jenis_kelamin' => $request->jenis_kelamin,
            'foto_profile' => $foto,
            'no_hp' => $request->no_hp
        ]);

        return redirect('profile')->with('message', 'Profile Berhasil di update.');
    }

    // public function upload_foto(Request $request)
    // {
    //     $id=Auth::user()->id;

    //     $this->validate($request, [
    //         'foto_profile' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:5000',
    //     ]);

    //     $image = $request->file('foto_profile');
    //     $filename = time() . '.' . $image->getClientOriginalExtension();
    //     $path = public_path('img/profile/' . $filename);

    //     // Resize gambar
    //     Image::make($image->getRealPath())
    //         ->resize(300, 300)
    //         //->crop(300, 300, 75, 50) // contoh crop, parameter: width, height, x-axis, y-axis
    //         ->save($path);


    //     $user = User::find($id)->update([
    //         'foto_profile' => $filename,
    //     ]);
    //     return back()->with('success', 'Image uploaded successfully');
    // }
}
