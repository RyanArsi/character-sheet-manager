<?php

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignInviteController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\ProfileController;
use App\Livewire\CharacterSheet;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/fichas', [CharacterController::class, 'create'])->name('fichas.criar');
    Route::get('/fichas/{character}/editar', CharacterSheet::class)->name('fichas.editar');
    Route::post('/fichas/{character}/autosave', [CharacterController::class, 'autosave'])->name('fichas.autosave');

    // Campanhas
    Route::get('/campanhas', [CampaignController::class, 'index'])->name('campanhas.index');
    Route::post('/campanhas', [CampaignController::class, 'store'])->name('campanhas.criar');
    Route::get('/campanhas/{campaign}', [CampaignController::class, 'show'])->name('campanhas.ver');
    Route::delete('/campanhas/{campaign}', [CampaignController::class, 'destroy'])->name('campanhas.excluir');
    Route::post('/campanhas/{campaign}/regenerar-convite', [CampaignController::class, 'regenerateToken'])->name('campanhas.regenerar-convite');
    Route::delete('/campanhas/{campaign}/fichas/{character}', [CampaignController::class, 'removeCharacter'])->name('campanhas.remover-ficha');
    Route::delete('/campanhas/{campaign}/membros/{user}', [CampaignController::class, 'banMember'])->name('campanhas.banir-membro');

    // Convite para entrar numa campanha
    Route::get('/convite/{token}', [CampaignInviteController::class, 'show'])->name('convite.ver');
    Route::post('/convite/{token}', [CampaignInviteController::class, 'join'])->name('convite.entrar');
});

require __DIR__.'/auth.php';
