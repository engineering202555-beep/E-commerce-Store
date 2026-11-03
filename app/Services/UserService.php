<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class UserService
{

    private function appendRolesAndPermissionsNO($user)
    {
     $roles=[];
     foreach ($user->roles as $role) {
         $roles[] = $role->name;
     }
     unset($user['roles']);
     $user['roles']=$roles;

     $permissions=[];
     foreach ($user->permissions as $permission) {
         $permissions[] = $permission->name;
     }
     unset($user['permissions']);
     $user['permissions']=$permissions;

     return $user;
    }

    public function RegisterWaiterNO($request)
    {
        $validateData= Validator::make($request->all(), [
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:6'],
            'username' => ['required', 'string', 'max:255','unique:waiters,username'],
        ]);
        if ($validateData->fails()) {
            return response()->json(['errors' => $validateData->errors()], 422);
        }
        $user = User::create([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            //'role' => 'waiter'
        ]);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed'
            ]);
        }
        $waiterRole=Role::query()->where('name','waiter')->first();
        $user->assignRole($waiterRole);

        $Permissions=$waiterRole->permissions->pluck('name')->toArray();
        $user->givePermissionTo($Permissions);
        $user->load('roles','permissions');

        $user->waiter()->create([
            'username' => $request->username
        ]);
        $user=User::query()->find($user->id);
        $user=$this->appendRolesAndPermissions($user);
        $user['token'] = $user->createToken('Waiter Access Token')->plainTextToken;

        $message='Waiter hired successfully';

        return ['user'=>$user,'message'=>$message];
    }

    public function loginWaiterNO(Request $request)
    {
        $loginData = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $loginData['email'])->first();

        if(!is_null($user)){
            if (! $user || ! Hash::check($loginData['password'], $user->password)) {

                $message = ['Could not login with provided credentials.'];
                $code=401;

            }else{
                $user=$this->appendRolesAndPermissions($user);
                $user['token'] = $user->createToken('Waiter Access Token')->plainTextToken;
                $message = 'Login successful';
                $code=200;
            }
        }else{
            $message = 'User not found';
            $code=404;
        }
        return [
            'user'=>$user,
            'message' => $message,
            'code' => $code,
        ];
    }
    public function logoutNO()
    {
        $user=Auth::user();
        if(!is_null($user)){
          Auth::user()->currentAccessToken()->delete();
            $message = 'Logged out successfully';
            $code=200;
        }else{
            $message = 'Invalid Token';
            $code=404;
        }
        return ['user'=>$user,
                'message' => $message,
                'code' => $code
        ];
    }


}
