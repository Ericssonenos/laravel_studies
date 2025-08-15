<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\UsuarioController;
use App\Http\Controllers\Auth\GrupoController;
use App\Http\Controllers\Auth\PapelController;
use App\Http\Controllers\Auth\PermissaoController;

Route::prefix('auth')->group(function () {
    // Usuarios
    Route::get('usuarios', [UsuarioController::class, 'index']);
    Route::post('usuarios', [UsuarioController::class, 'store']);
    Route::post('usuarios/{id}/grupos', [UsuarioController::class, 'atribuirGrupo']);
    Route::delete('usuarios/{id}/grupos', [UsuarioController::class, 'removerGrupo']);
    Route::get('usuarios/{id}/grupos', [UsuarioController::class, 'listarGrupos']);
    Route::post('usuarios/{id}/papeis', [UsuarioController::class, 'atribuirPapel']);
    Route::delete('usuarios/{id}/papeis', [UsuarioController::class, 'removerPapel']);
    Route::get('usuarios/{id}/papeis', [UsuarioController::class, 'listarPapeis']);
    Route::post('usuarios/{id}/permissoes', [UsuarioController::class, 'atribuirPermissao']);
    Route::delete('usuarios/{id}/permissoes', [UsuarioController::class, 'removerPermissao']);
    Route::get('usuarios/{id}/permissoes', [UsuarioController::class, 'listarPermissoes']);

    // Grupos
    Route::get('grupos', [GrupoController::class, 'index']);
    Route::post('grupos', [GrupoController::class, 'store']);
    Route::put('grupos/{id}', [GrupoController::class, 'update']);
    Route::delete('grupos/{id}', [GrupoController::class, 'destroy']);
    Route::post('grupos/{id}/papeis', [GrupoController::class, 'atribuirPapel']);
    Route::delete('grupos/{id}/papeis', [GrupoController::class, 'removerPapel']);
    Route::get('grupos/{id}/papeis', [GrupoController::class, 'listarPapeis']);

    // Papeis
    Route::get('papeis', [PapelController::class, 'index']);
    Route::post('papeis', [PapelController::class, 'store']);
    Route::put('papeis/{id}', [PapelController::class, 'update']);
    Route::delete('papeis/{id}', [PapelController::class, 'destroy']);
    Route::post('papeis/{id}/permissoes', [PapelController::class, 'atribuirPermissao']);
    Route::delete('papeis/{id}/permissoes', [PapelController::class, 'removerPermissao']);
    Route::get('papeis/{id}/permissoes', [PapelController::class, 'listarPermissoes']);

    // Permissoes
    Route::get('permissoes', [PermissaoController::class, 'index']);
    Route::post('permissoes', [PermissaoController::class, 'store']);
    Route::put('permissoes/{id}', [PermissaoController::class, 'update']);
    Route::delete('permissoes/{id}', [PermissaoController::class, 'destroy']);
});

