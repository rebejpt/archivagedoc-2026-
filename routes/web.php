<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Page d'accueil publique avec formulaire de demande
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Route pour le formulaire de demande d'accès
Route::post('/access-request', function (Request $request) {
    // Valider la demande
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'company' => 'required|string|max:255',
        'reason' => 'required|string'
    ]);
    
    //  TODO: Envoyer un email à l'administrateur
    // Mail::to('admin@archidoc.com')->send(new AccessRequestMail($request->all()));
    
    // TODO: Stocker la demande en base de données si nécessaire
    
    return response()->json(['message' => 'Demande envoyée avec succès']);
})->name('access.request');

// Routes protégées par authentification
Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // Routes Documents
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/', function () {
            return Inertia::render('Documents/Index');
        })->name('index');
        
        Route::get('/upload', function () {
            return Inertia::render('Documents/Upload');
        })->name('upload');
        
        Route::get('/{id}/edit', function ($id) {
            return Inertia::render('Documents/Edit', ['id' => $id]);
        })->name('edit');
        
        Route::get('/{id}', function ($id) {
            return Inertia::render('Documents/Show', ['id' => $id]);
        })->name('show');
    });

    // Routes Catégories
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', function () {
            return Inertia::render('Categories/Index');
        })->name('index');
        
        Route::get('/create', function () {
            return Inertia::render('Categories/Create');
        })->name('create');
        
        Route::get('/{id}/edit', function ($id) {
            return Inertia::render('Categories/Edit', ['id' => $id]);
        })->name('edit');
    });

    // Routes Tags
    Route::prefix('tags')->name('tags.')->group(function () {
        Route::get('/', function () {
            return Inertia::render('Tags/Index');
        })->name('index');
        
        Route::get('/create', function () {
            return Inertia::render('Tags/Create');
        })->name('create');
        
        Route::get('/{id}/edit', function ($id) {
            return Inertia::render('Tags/Edit', ['id' => $id]);
        })->name('edit');
    });
        // ROUTES PROFIL - AJOUTE ÇA !
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Routes admin seulement
    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', function () {
            return Inertia::render('Users/Index');
        })->name('users.index');
        
        Route::get('/users/create', function () {
            return Inertia::render('Users/Create');
        })->name('users.create');
        
        Route::get('/users/{id}/edit', function ($id) {
            return Inertia::render('Users/Edit', ['id' => $id]);
        })->name('users.edit');
        
        Route::get('/access-logs', function () {
            return Inertia::render('AccessLogs/Index');
        })->name('logs.index');

        Route::get('/documents/{id}', function ($id) {
            return Inertia::render('Documents/Show', ['id' => $id]);
        })->name('documents.show');     
    });
    
    // Routes utilisateurs (accès direct depuis le sidebar)
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/users', function () {
            return Inertia::render('Users/Index');
        });
        
        Route::get('/users/create', function () {
            return Inertia::render('Users/Create');
        });
        
        Route::get('/users/{id}/edit', function ($id) {
            return Inertia::render('Users/Edit', ['id' => $id]);
        });
    });
});

// Routes API-like via web (protégées par session)
Route::middleware(['auth'])->prefix('web-api')->name('api.')->group(function () {
    // Statistiques
    Route::get('/stats', [StatsController::class, 'index'])->name('stats.index');
    
    // Documents
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/', [DocumentController::class, 'index'])->name('index');
        Route::post('/', [DocumentController::class, 'store'])->name('store');
        Route::get('/{document}', [DocumentController::class, 'show'])->name('show');
        Route::put('/{document}', [DocumentController::class, 'update'])->name('update');
        // Route::delete('/{document}', [DocumentController::class, 'destroy'])->name('destroy');
        Route::get('/{document}/download', [DocumentController::class, 'download'])->name('download');
        // Route::get('/documents/{id}/edit',  [DocumentController::class, 'edit'])->name('edit');
        // modofication
        // Route::get('/upload', [DocumentController::class, 'create'])->name('upload');
        Route::get('/{id}/edit', [DocumentController::class, 'edit'])->name('edit');
        // pour le afficher le details du document - historiques
        Route::get('/{document}/history', [DocumentController::class, 'history']);
    });
    
    // Catégories
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::post('/', [CategoryController::class, 'store'])->name('store');
        Route::get('/{category}', [CategoryController::class, 'show'])->name('show');
        Route::put('/{category}', [CategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
    });
    
    // Tags
    Route::prefix('tags')->name('tags.')->group(function () {
        Route::get('/', [TagController::class, 'index'])->name('index');
        Route::get('/popular', [TagController::class, 'popular'])->name('popular');
        Route::post('/', [TagController::class, 'store'])->name('store');
        Route::get('/{tag}', [TagController::class, 'show'])->name('show');
        Route::put('/{tag}', [TagController::class, 'update'])->name('update');
        Route::delete('/{tag}', [TagController::class, 'destroy'])->name('destroy');
       
    });
    
    // Utilisateurs (admin seulement)
    Route::middleware(['role:admin'])->prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/list', [UserController::class, 'list']);//ADD
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::delete('/{user}', [UserController::class, 'destroy']);
        Route::patch('/{user}/toggle-active', [UserController::class, 'toggleActive']);//ADD
        Route::patch('/roles', [UserController::class, 'roles']);//ADD
    });
    
    // Route pour récupérer les rôles (GET)
    Route::middleware(['auth'])->get('/roles', [UserController::class, 'roles']);
});

// Routes d'authentification (Breeze)
// Route pour le changement de mot de passe (dans auth.php)
require __DIR__.'/auth.php';