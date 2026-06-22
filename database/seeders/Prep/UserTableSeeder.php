<?php

namespace Database\Seeders\Prep;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserTableSeeder extends Seeder
{

    public function run(): void
    {
        $users = DB::connection('mysql_old_troves')
            ->table('users')
            ->select('id', 'name', 'email', 'password')
            ->get();

        foreach ($users as $user) {
            if ($user->id > 0) {

                User::create([
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'password' => $user->password ?? bcrypt('awskledfjnmakdjfkaskdlfjankdsfnlasjdn'),
                ]);
            }
        }
    }
}
