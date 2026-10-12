<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdministrationController extends Controller
{
    public function users(Request $request)
    {
        abort_unless($request->user()->isSupport(), 403);

        return Inertia::render('Support/Users', ['users' => User::orderBy('name')->paginate(20)]);
    }

    public function storeUser(Request $request)
    {
        abort_unless($request->user()->isSupport(), 403);
        $data = $request->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:180|unique:users', 'password' => ['required', Password::min(10)->letters()->numbers()], 'role' => 'required|in:user,support']);
        User::create([...$data, 'is_active' => true]);

        return back()->with('success', 'Cuenta creada.');
    }

    public function updateUser(Request $request, User $user)
    {
        abort_unless($request->user()->isSupport(), 403);
        $data = $request->validate(['role' => 'required|in:user,support', 'is_active' => 'required|boolean']);
        DB::transaction(function () use ($user, $data) {
            $supports = User::where('role', 'support')->where('is_active', true)->orderBy('id')->lockForUpdate()->get();
            $user = User::lockForUpdate()->findOrFail($user->id);
            if ($user->isSupport() && (! $data['is_active'] || $data['role'] !== 'support')) {
                if ($supports->count() <= 1) {
                    throw ValidationException::withMessages(['role' => 'Debe existir al menos un soporte activo.']);
                }
                if (Ticket::where('assigned_to', $user->id)->where('status', '!=', 'completed')->exists()) {
                    throw ValidationException::withMessages(['role' => 'Reasigna sus tickets abiertos antes de desactivar o cambiar su rol.']);
                }
            }
            $user->update($data);
        });

        if ($user->id === $request->user()->id && (! $data['is_active'] || $data['role'] !== 'support')) {
            return redirect('/dashboard')->with('success', 'Cuenta actualizada.');
        }

        return back()->with('success', 'Cuenta actualizada.');
    }

    public function categories(Request $request)
    {
        abort_unless($request->user()->isSupport(), 403);

        return Inertia::render('Support/Categories', ['categories' => Category::orderBy('name')->get()]);
    }

    public function storeCategory(Request $request)
    {
        abort_unless($request->user()->isSupport(), 403);
        Category::create($request->validate(['name' => 'required|string|max:80|unique:categories']));

        return back()->with('success', 'Categoría creada.');
    }

    public function updateCategory(Request $request, Category $category)
    {
        abort_unless($request->user()->isSupport(), 403);
        $category->update($request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('categories')->ignore($category->id)], 'is_active' => 'required|boolean']));

        return back()->with('success', 'Categoría actualizada.');
    }
}
