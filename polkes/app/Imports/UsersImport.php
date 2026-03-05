<?php

namespace App\Imports;

use App\UserImport;
use Maatwebsite\Excel\Concerns\ToModel;
use Illuminate\Support\Facades\Hash;

class UsersImport implements ToModel
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new UserImport([
           'username'    => $row[0], 
           'name'     => $row[1],
           'email'    => $row[2], 
           'jurusan'    => $row[3], 
           'password' => Hash::make($row[4]),
           'role' => $row[5],
           'foto_profile' => 'default.png'
        ]);
    }
}
