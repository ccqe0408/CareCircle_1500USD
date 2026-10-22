<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private function session(User $user): array
    {
        return ['token' => $user->createToken('carecircle-mobile', ['mobile'], now()->addDays(7))->plainTextToken, 'user' => $this->user($user)];
    }

    private function user(User $user): array
    {
        return $user->only(['id', 'name', 'email', 'phone', 'is_admin']) + ['volunteer_profile' => $user->volunteerProfile];
    }

    public function register(Request $r)
    {
        $v = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users', 'phone' => 'required|string|max:30', 'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()]]);
        $u = User::create($v);

        return response()->json($this->session($u), 201);
    }

    public function login(Request $r)
    {
        $v = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        $u = User::where('email', $v['email'])->first();
        if (! $u || ! Hash::check($v['password'], $u->password)) {
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }
        abort_if($u->suspended, 403, 'Your account has been suspended.');

        return $this->session($u);
    }

    public function me(Request $r)
    {
        return $this->user($r->user());
    }

    public function update(Request $r)
    {
        $v = $r->validate(['name' => 'sometimes|required|string|max:100', 'phone' => 'sometimes|required|string|max:30']);
        $r->user()->update($v);

        return $this->user($r->user()->fresh());
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()?->delete();

        return ['message' => 'Signed out'];
    }
}
