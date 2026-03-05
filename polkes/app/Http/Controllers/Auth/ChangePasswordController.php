<?php
 
namespace App\Http\Controllers\Auth;
 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use Auth;
use DB;
 
class ChangePasswordController extends Controller
{
    public function __construct()
    {
         $this->middleware('auth');
    }

    public function showChangePasswordForm()
    {
        $user = Auth::user();
        $sql = "select c.name
                from users a
                join model_has_roles b on (a.id=b.model_id)
                join roles c on (b.role_id=c.id)
                where b.model_id=$user->id";
        $roles = DB::select($sql);
        return view('auth.passwords.changepassword',compact('user','roles'));
    }
     
    public function changePassword(Request $request)
    { 
        if (!(Hash::check($request->get('current-password'), Auth::user()->password))) {
        // The passwords matches
        return redirect()->back()->with("error","Kata sandi Anda saat ini tidak cocok dengan kata sandi yang Anda berikan. Silakan coba lagi.");
        }
        if(strcmp($request->get('current-password'), $request->get('new-password')) == 0){
        //Current password and new password are same
        return redirect()->back()->with("error","Kata Sandi Baru tidak boleh sama dengan kata sandi Anda saat ini. Silakan pilih kata sandi lain.");
        }
        if(!(strcmp($request->get('new-password'), $request->get('new-password-confirm'))) == 0){
            //New password and confirm password are not same
            return redirect()->back()->with("error","Kata Sandi Baru harus sama dengan kata sandi Anda yang telah dikonfirmasi. Silakan ketik ulang kata sandi baru.");
        }
        //Change Password
        $user = Auth::user();
        $user->password = bcrypt($request->get('new-password'));
        $user->save();
         
        return redirect()->back()->with("success","Password changed successfully !");
     
    }
}