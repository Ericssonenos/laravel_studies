<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\UsuarioController;
use App\Http\Controllers\Auth\GrupoController;
use App\Http\Controllers\Auth\PapelController;
use App\Http\Controllers\Auth\PermissaoController;

Route::prefix('auth')->group(function () {
    // Usuarios
    Route::get('usuarios', [UsuarioController::class, 'Lista']);
    Route::post('usuarios', [UsuarioController::class, 'Criar']);
    Route::post('usuarios/grupos', [UsuarioController::class, 'AtribuirGrupo']);
    Route::post('usuarios/papeis', [UsuarioController::class, 'AtribuirPapel']);
    Route::post('usuarios/permissoes', [UsuarioController::class, 'AtribuirPermissao']);
    Route::delete('usuarios/grupos', [UsuarioController::class, 'RemoverGrupo']);
    Route::get('usuarios/grupos', [UsuarioController::class, 'ListarGrupos']);
    Route::delete('usuarios/papeis', [UsuarioController::class, 'RemoverPapel']);
    Route::get('usuarios/papeis', [UsuarioController::class, 'ListarPapeis']);
    Route::delete('usuarios/permissoes', [UsuarioController::class, 'RemoverPermissao']);
    Route::get('usuarios/permissoes', [UsuarioController::class, 'ListarPermissoes']);

    // Grupos
    Route::get('grupos', [GrupoController::class, 'Lista']);
    Route::post('grupos', [GrupoController::class, 'Criar']);
    Route::put('grupos', [GrupoController::class, 'Update']);
    Route::delete('grupos', [GrupoController::class, 'Destroy']);
    Route::post('grupos/papeis', [GrupoController::class, 'AtribuirPapel']);
    Route::delete('grupos/papeis', [GrupoController::class, 'RemoverPapel']);
    Route::get('grupos/papeis', [GrupoController::class, 'ListarPapeis']);

    // Papeis
    Route::get('papeis', [PapelController::class, 'Lista']);
    Route::post('papeis', [PapelController::class, 'Criar']);
    Route::put('papeis', [PapelController::class, 'Update']);
    Route::delete('papeis', [PapelController::class, 'Destroy']);
    Route::post('papeis/permissoes', [PapelController::class, 'AtribuirPermissao']);
    Route::delete('papeis/permissoes', [PapelController::class, 'RemoverPermissao']);
    Route::get('papeis/permissoes', [PapelController::class, 'ListarPermissoes']);

    // Permissoes
    Route::get('permissoes', [PermissaoController::class, 'Lista']);
    Route::post('permissoes', [PermissaoController::class, 'Criar']);
    Route::put('permissoes', [PermissaoController::class, 'Update']);
    Route::delete('permissoes', [PermissaoController::class, 'Destroy']);
});

