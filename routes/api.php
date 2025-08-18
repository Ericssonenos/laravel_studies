<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\UsuarioController;
use App\Http\Controllers\Auth\GrupoController;
use App\Http\Controllers\Auth\PapelController;
use App\Http\Controllers\Auth\PermissaoController;

Route::prefix('auth')->group(function () {
    // Usuarios
    Route::get('usuarios', [UsuarioController::class, 'Lista']);
    Route::get('grupos', [GrupoController::class, 'Lista']);
    Route::get('papeis', [PapelController::class, 'Lista']);



    Route::post('usuarios/grupos', [UsuarioController::class, 'AtribuirGrupo']);
    Route::post('usuarios/papeis', [UsuarioController::class, 'AtribuirPapel']);


    Route::post('usuarios', [UsuarioController::class, 'Criar']);
    Route::post('papeis', [PapelController::class, 'Criar']);
    Route::post('grupos', [GrupoController::class, 'Criar']);




    Route::delete('usuarios/{id}/grupos', [UsuarioController::class, 'RemoverGrupo']);
    Route::get('usuarios/{id}/grupos', [UsuarioController::class, 'ListarGrupos']);

    Route::delete('usuarios/{id}/papeis', [UsuarioController::class, 'RemoverPapel']);
    Route::get('usuarios/{id}/papeis', [UsuarioController::class, 'ListarPapeis']);
    Route::post('usuarios/{id}/permissoes', [UsuarioController::class, 'AtribuirPermissao']);
    Route::delete('usuarios/{id}/permissoes', [UsuarioController::class, 'RemoverPermissao']);
    Route::get('usuarios/{id}/permissoes', [UsuarioController::class, 'ListarPermissoes']);

    // Grupos

    Route::put('grupos/{id}', [GrupoController::class, 'update']);
    Route::delete('grupos/{id}', [GrupoController::class, 'destroy']);
    Route::post('grupos/{id}/papeis', [GrupoController::class, 'atribuirPapel']);
    Route::delete('grupos/{id}/papeis', [GrupoController::class, 'removerPapel']);
    Route::get('grupos/{id}/papeis', [GrupoController::class, 'listarPapeis']);

    // Papeis


    Route::put('papeis/{id}', [PapelController::class, 'update']);
    Route::delete('papeis/{id}', [PapelController::class, 'destroy']);
    Route::post('papeis/{id}/permissoes', [PapelController::class, 'atribuirPermissao']);
    Route::delete('papeis/{id}/permissoes', [PapelController::class, 'removerPermissao']);
    Route::get('papeis/{id}/permissoes', [PapelController::class, 'listarPermissoes']);

    // Permissoes
    Route::get('permissoes', [PermissaoController::class, 'Lista']);
    Route::post('permissoes', [PermissaoController::class, 'Criar']);
    Route::put('permissoes/{id}', [PermissaoController::class, 'update']);
    Route::delete('permissoes/{id}', [PermissaoController::class, 'destroy']);
});

